<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\WalletTokenService;

class WalletTokenSnapshotCommand extends Command
{
    protected $signature = 'web3:wallet:snapshot
                            {token : Token ID}
                            {addresses* : One or more wallet addresses or IDs}
                            {--allowance= : Optional spender address to snapshot allowances too}';

    protected $description = 'Snapshot token balances for wallets (and optionally allowances).';

    public function handle(WalletTokenService $service): int
    {
        $tokenId = (int) $this->argument('token');
    $addresses = (array) $this->argument('addresses');
        $spender = $this->option('allowance');

        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token {$tokenId} not found");

            return self::FAILURE;
        }

        // Resolve any numeric inputs to wallet addresses to support IDs
        $resolved = collect($addresses)->map(function ($arg) {
            $s = (string) $arg;
            if (ctype_digit($s)) {
                $w = \Roberts\Web3Laravel\Models\Wallet::find((int) $s);
                return $w?->getRawOriginal('address') ?? $s;
            }
            return $s;
        })->all();

        $this->info('Snapshotting balances...');
        $rows = $service->snapshotBalances($token, $resolved);
        $this->line('Updated rows: '.count($rows));

        // Print a compact table of results per wallet
        if (count($rows) > 0) {
            $display = collect($rows)->map(function ($row) use ($token) {
                /** @var \Roberts\Web3Laravel\Models\WalletToken $row */
                $wallet = $row->wallet; 
                return [
                    'wallet_id' => $wallet->id,
                    'address' => $wallet->address,
                    'protocol' => (string) $wallet->protocol->value,
                    'balance' => (string) $row->balance,
                    'last_synced' => optional($row->last_synced_at)->toDateTimeString(),
                ];
            })->all();
            $this->table(['Wallet', 'Address', 'Protocol', 'Balance (raw)', 'Last Synced'], $display);
        }

    if ($spender) {
            $this->info('Snapshotting allowances...');
            $count = 0;
            foreach ($rows as $row) {
                $service->snapshotAllowance($token, $row->wallet, $spender);
                $count++;
            }
            $this->line('Allowances checked for '.$count.' wallets');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
