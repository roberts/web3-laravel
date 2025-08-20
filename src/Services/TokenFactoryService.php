<?php

namespace Roberts\Web3Laravel\Services;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class TokenFactoryService
{
    /**
     * Create a chain-agnostic fungible token via async transaction pipeline.
     * The system will sign using the provided signer wallet (must be custodial/shared).
     *
     * Options keys:
     * - blockchain_id?: int (preferred) – resolves protocol
     * - protocol?: BlockchainProtocol (or string) – used if no blockchain_id
     * - signer_wallet_id: int
     * - name: string
     * - symbol: string
     * - decimals: int
     * - initial_supply: string (raw base units)
     * - recipient_address?: string
     * - recipient_wallet_id?: int
     * - create_recipient_ata?: bool (Solana)
     * - mint_authority_wallet_id?: int (defaults to signer)
     * - freeze_authority_wallet_id?: int|null
     * - meta?: array (additional metadata to persist on token)
     */
    public function createFungibleToken(array $options): Transaction
    {
        $signer = Wallet::findOrFail((int) ($options['signer_wallet_id'] ?? 0));
        if (! in_array($signer->wallet_type->value, [WalletType::CUSTODIAL->value, WalletType::SHARED->value], true)) {
            throw new \InvalidArgumentException('Signer must be a custodial or shared wallet');
        }

        $blockchain = null;
        $protocol = $signer->protocol; // default from signer
        if (isset($options['blockchain_id'])) {
            $blockchain = Blockchain::findOrFail((int) $options['blockchain_id']);
            $protocol = $blockchain->protocol;
        } elseif (isset($options['protocol'])) {
            $protocol = $options['protocol'] instanceof BlockchainProtocol
                ? $options['protocol']
                : BlockchainProtocol::from(strtolower((string) $options['protocol']));
        }
        if ($protocol !== $signer->protocol) {
            throw new \InvalidArgumentException('Signer wallet protocol does not match requested protocol');
        }

        $name = (string) ($options['name'] ?? '');
        $symbol = (string) ($options['symbol'] ?? '');
        $decimals = (int) ($options['decimals'] ?? 0);
        $initial = (string) ($options['initial_supply'] ?? '0');
        if ($name === '' || $symbol === '') {
            throw new \InvalidArgumentException('Token name and symbol are required');
        }
        if ($decimals < 0) {
            throw new \InvalidArgumentException('Invalid decimals');
        }

        $recipientAddress = (string) ($options['recipient_address'] ?? '');
        $recipientWalletId = null;
        if (! $recipientAddress && isset($options['recipient_wallet_id'])) {
            $recipient = Wallet::find((int) $options['recipient_wallet_id']);
            if ($recipient) {
                $recipientAddress = $recipient->getRawOriginal('address');
                $recipientWalletId = $recipient->id;
            }
        }

        $standard = match ($protocol) {
            BlockchainProtocol::SOLANA => 'spl',
            BlockchainProtocol::SUI => 'sui',
            BlockchainProtocol::HEDERA => 'hts',
            BlockchainProtocol::CARDANO => 'cardano',
            BlockchainProtocol::XRPL => 'xrpl',
            BlockchainProtocol::TON => 'jetton',
            default => 'erc20',
        };

        $createTx = function () use ($signer, $blockchain, $name, $symbol, $decimals, $initial, $recipientAddress, $recipientWalletId, $standard, $options) {
            return Transaction::create([
                'wallet_id' => $signer->id,
                'blockchain_id' => $blockchain?->id,
                'contract_id' => null,
                'to' => null,
                'from' => $signer->getRawOriginal('address'),
                'function' => null,
                'function_params' => [
                    'operation' => 'create_fungible_token',
                ],
                'value' => '0',
                'data' => null,
                'status' => 'pending',
                'meta' => [
                    'standard' => $standard,
                    'token' => [
                        'name' => $name,
                        'symbol' => $symbol,
                        'decimals' => $decimals,
                        'initial_supply' => $initial,
                        'extra' => (array) ($options['meta'] ?? []),
                    ],
                    'recipient' => [
                        'address' => $recipientAddress,
                        'wallet_id' => $recipientWalletId,
                        'create_ata' => (bool) ($options['create_recipient_ata'] ?? true),
                    ],
                    'authorities' => [
                        'mint_wallet_id' => (int) ($options['mint_authority_wallet_id'] ?? $signer->id),
                        'freeze_wallet_id' => $options['freeze_authority_wallet_id'] ?? null,
                    ],
                ],
            ]);
        };

        // In tests, avoid dispatching events to the queue unless explicitly desired
        $tx = app()->runningUnitTests() ? Model::withoutEvents($createTx) : $createTx();

        return $tx;
    }
}
