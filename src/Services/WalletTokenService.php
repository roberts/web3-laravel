<?php

namespace Roberts\Web3Laravel\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Roberts\Web3Laravel\Events\WalletTokenAllowanceUpdated;
use Roberts\Web3Laravel\Events\WalletTokenBalanceUpdated;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletToken;

class WalletTokenService
{
    public function __construct(protected TokenService $tokenService) {}

    /**
     * Snapshot balances for a set of wallet addresses against a token.
     * Returns collection of WalletToken rows updated.
     * Emits WalletTokenBalanceUpdated events when a change is detected.
     *
     * @param  array<int,string>|Collection<int,string>  $walletAddresses
     */
    public function snapshotBalances(Token $token, $walletAddresses): Collection
    {
        $addresses = collect($walletAddresses)->filter()->map(function ($addr) {
            // Normalize EVM, leave non-hex as-is
            return preg_match('/^(0x)?[0-9a-fA-F]{40}$/', (string) $addr)
                ? strtolower($addr)
                : (string) $addr;
        })->values();
        $now = Carbon::now();
        $updated = collect();

        // Fetch current DB rows for performance
        $existing = WalletToken::query()
            ->where('token_id', $token->id)
            ->whereHas('wallet', fn ($q) => $q->whereIn('address', $addresses))
            ->with('wallet')
            ->get()
            ->keyBy(fn ($wt) => strtolower($wt->wallet->address));

        // Map addresses to Wallet models (normalize lowercase for EVM; non-hex kept as-is)
        $wallets = Wallet::query()->whereIn('address', $addresses)->get()->keyBy(function ($w) {
            $addr = $w->getRawOriginal('address');
            return preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $addr) ? strtolower($addr) : $addr;
        });

        foreach ($addresses as $address) {
            $addr = preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $address) ? strtolower($address) : $address;
            $wallet = $wallets->get($addr);
            if (! $wallet) {
                // Skip unknown wallets in our DB
                continue;
            }

            $onChain = $this->tokenService->balanceOf($token, $addr);

            /** @var WalletToken|null $row */
            $row = $existing->get($addr) ?? new WalletToken([
                'wallet_id' => $wallet->id,
                'token_id' => $token->id,
            ]);

            $old = $row->exists ? (string) $row->balance : '0';

            if (! $row->exists) {
                $row->balance = $onChain;
                $row->last_synced_at = $now;
                $row->save();
                $row->refresh();

                WalletTokenBalanceUpdated::dispatch($wallet, $token, $old, $onChain, $row);
            } elseif ($old !== (string) $onChain) {
                $row->balance = $onChain;
                $row->last_synced_at = $now;
                $row->save();
                $row->refresh();

                WalletTokenBalanceUpdated::dispatch($wallet, $token, $old, $onChain, $row);
            } else {
                // No change; still bump last_synced_at
                $row->last_synced_at = $now;
                $row->save();
            }

            $updated->push($row);
        }

        return $updated;
    }

    /**
     * Convenience method to snapshot a wallet's allowance to a spender and emit event on change.
     */
    public function snapshotAllowance(Token $token, Wallet $owner, string $spender): string
    {
        $current = $this->tokenService->allowance($token, $owner->address, $spender);

        $meta = ['spender' => strtolower($spender)];

        $row = WalletToken::firstOrCreate(
            ['wallet_id' => $owner->id, 'token_id' => $token->id],
            ['balance' => '0']
        );

        $old = (string) ($row->meta['allowances'][$meta['spender']] ?? '0');

        if ($old !== (string) $current) {
            $row->meta = array_replace_recursive($row->meta ?? [], [
                'allowances' => [
                    $meta['spender'] => $current,
                ],
            ]);
            $row->save();

            WalletTokenAllowanceUpdated::dispatch($owner, $spender, $token, $old, $current);
        }

        return $current;
    }
}
