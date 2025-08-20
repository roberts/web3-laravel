<?php

namespace Roberts\Web3Laravel\Protocols\Sui;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;

// not used but kept for parity

class SuiProtocolAdapter implements ProtocolAdapter, ProtocolTransactionAdapter
{
    public function __construct(private KeyEngineInterface $keys, private SuiJsonRpcClient $rpc) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::SUI;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Sui ed25519');
        }
        [$skHex, $pkBytes] = $this->keys->randomKeypair(BlockchainProtocol::SUI, 'ed25519');
        $address = $this->keys->publicKeyToAddress(BlockchainProtocol::SUI, data_get($attributes, 'network', 'mainnet'), 'ed25519', $pkBytes);
        $data = array_merge([
            'address' => $address,
            'key' => $skHex,
            'public_key' => bin2hex($pkBytes),
            'key_scheme' => 'ed25519',
            'protocol' => BlockchainProtocol::SUI,
            'is_active' => true,
        ], $attributes);
        if ($owner instanceof Model) {
            $data['owner_id'] = $owner->getKey();
        }
        if ($blockchain) {
            $data['blockchain_id'] = $blockchain->getKey();
        }

        return Wallet::create($data);
    }

    public function getNativeBalance(Wallet $wallet): string
    {
        return '0';
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Sui signing');
        }
        // Choose a coin for spending and build tx via RPC helper
        $coins = $this->rpc->getCoins($from->address)['data'] ?? [];
        if (empty($coins)) {
            throw new \RuntimeException('No SUI coins available to cover transfer');
        }
        $coinId = (string) ($coins[0]['coinObjectId'] ?? '');
        if ($coinId === '') {
            throw new \RuntimeException('Invalid SUI coin reference');
        }
        $gasPrice = $this->rpc->getReferenceGasPrice();
        // choose a conservative gas budget
        $gasBudget = max(1000, (int) ($gasPrice * 10));

        $build = $this->rpc->transferSui($from->address, $coinId, $toAddress, (int) $amount, (int) $gasBudget);
        $txBytes = (string) (data_get($build, 'txBytes') ?? '');
        if ($txBytes === '') {
            throw new \RuntimeException('Sui transferSui did not return txBytes');
        }

        // Sign and execute
        $secretHex = Crypt::decryptString($from->key);
        $secret = hex2bin($secretHex);
        if ($secret === false) {
            throw new \RuntimeException('Invalid Sui secret key encoding');
        }
        $sig = sodium_crypto_sign_detached(base64_decode($txBytes), $secret);
        $sigBase64 = base64_encode("\x00".$sig); // ed25519 flag

        $res = $this->rpc->executeTransactionBlock($txBytes, [$sigBase64]);
        $digest = (string) (data_get($res, 'digest') ?? '');
        if ($digest === '') {
            throw new \RuntimeException('Sui executeTransactionBlock failed');
        }

        return $digest;
    }

    public function normalizeAddress(string $address): string
    {
        return strtolower($address);
    }

    public function validateAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-f]{64}$/', strtolower($address));
    }

    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string
    {
        return '0';
    }

    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string
    {
        return '0';
    }

    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $from, string $toAddress, string $amount): string
    {
        // Phase 1: enqueue a generic transaction record to represent a Sui token transfer.
        // Real on-chain token transfers will be added when Sui coin-type transfers are wired.
        $create = function () use ($token, $from, $toAddress, $amount) {
            return \Roberts\Web3Laravel\Models\Transaction::create([
                'wallet_id' => $from->id,
                'contract_id' => $token->contract_id,
                'to' => $toAddress,
                'value' => (string) $amount,
                'data' => null,
                'function_params' => [
                    'operation' => 'sui_token_transfer',
                ],
                'meta' => [
                    'token_operation' => 'transfer',
                    'token_id' => $token->id,
                    'recipient' => $toAddress,
                    'amount' => $amount,
                    'sui' => [
                        'coin_type' => $token->contract->address ?? null,
                    ],
                ],
            ]);
        };

        $tx = app()->runningUnitTests() ? Model::withoutEvents($create) : $create();

        return (string) $tx->id;
    }

    public function approveToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $owner, string $spenderAddress, string $amount): string
    {
        throw new \RuntimeException('Not implemented');
    }

    public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $owner, string $spenderAddress): string
    {
        throw new \RuntimeException('Not implemented');
    }

    // -----------------------------
    // ProtocolTransactionAdapter (Sui)
    // -----------------------------
    public function prepareTransaction(Transaction $tx, Wallet $wallet): void
    {
        // If this is a token creation request, delegate to helper
        $op = (string) ($tx->function_params['operation'] ?? ($tx->meta['operation'] ?? ''));
        $standard = (string) ($tx->meta['standard'] ?? '');
        if ($op === 'create_fungible_token' && $standard === 'sui') {
            DeployToken::prepare($tx, $wallet, $this->rpc);

            return;
        }

        // Gather coin objects to use as inputs for a SUI coin transfer; cache reference gas price
        $meta = (array) ($tx->meta ?? []);
        $meta['sui'] = $meta['sui'] ?? [];
        try {
            $price = $this->rpc->getReferenceGasPrice();
            $meta['sui']['referenceGasPrice'] = $price;
        } catch (\Throwable) {
        }

        // Attempt to list coins; store first page for building a simple transfer
        try {
            $coins = $this->rpc->getCoins($wallet->address);
            $meta['sui']['coins'] = $coins['data'] ?? [];
        } catch (\Throwable) {
        }

        $tx->meta = $meta;
    }

    public function submitTransaction(Transaction $tx, Wallet $wallet): string
    {
        // Handle token creation via helper
        $op = (string) ($tx->function_params['operation'] ?? ($tx->meta['operation'] ?? ''));
        $standard = (string) ($tx->meta['standard'] ?? '');
        if ($op === 'create_fungible_token' && $standard === 'sui') {
            return DeployToken::submit($tx, $wallet, $this->rpc, $this->keys);
        }

        $to = (string) $tx->to;
        $amountStr = (string) ($tx->value ?? '');
        if ($to === '' || $amountStr === '') {
            throw new \InvalidArgumentException('Sui transaction requires destination and amount (MIST)');
        }
        // Build txBytes via RPC helper when not provided in meta
        $coins = (array) (($tx->meta['sui']['coins'] ?? []) ?: []);
        if (empty($coins)) {
            $coins = $this->rpc->getCoins($wallet->address)['data'] ?? [];
        }
        if (empty($coins)) {
            throw new \RuntimeException('No SUI coins available to cover transfer');
        }
        $coinId = (string) ($coins[0]['coinObjectId'] ?? '');
        $gasPrice = $this->rpc->getReferenceGasPrice();
        $gasBudget = max(1000, (int) ($gasPrice * 10));

        $txBytes = (string) (data_get($tx->meta, 'sui.txBytes') ?? '');
        if ($txBytes === '') {
            $build = $this->rpc->transferSui($wallet->address, $coinId, $to, (int) $amountStr, (int) $gasBudget);
            $txBytes = (string) (data_get($build, 'txBytes') ?? '');
            if ($txBytes === '') {
                throw new \RuntimeException('Sui transferSui did not return txBytes');
            }
        }

        // Sign & execute as above
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Sui signing');
        }
        $secretHex = Crypt::decryptString($wallet->key);
        $secret = hex2bin($secretHex);
        if ($secret === false) {
            throw new \RuntimeException('Invalid Sui secret key encoding');
        }
        $sig = sodium_crypto_sign_detached(base64_decode($txBytes), $secret);
        $sigBase64 = base64_encode("\x00".$sig);
        $res = $this->rpc->executeTransactionBlock($txBytes, [$sigBase64]);
        $digest = (string) (data_get($res, 'digest') ?? '');
        if ($digest === '') {
            throw new \RuntimeException('Sui executeTransactionBlock failed');
        }

        return $digest;
    }

    public function checkConfirmations(Transaction $tx, Wallet $wallet): array
    {
        $digest = (string) $tx->tx_hash;
        if ($digest === '') {
            return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
        }
        try {
            $res = $this->rpc->getTransactionBlock($digest);
            $effects = data_get($res, 'effects');
            $checkpoint = isset($res['checkpoint']) ? (int) $res['checkpoint'] : null;
            $latest = $this->rpc->getLatestCheckpointSequenceNumber();
            $confs = ($checkpoint !== null) ? max(0, $latest - $checkpoint + 1) : 0;
            $required = (int) config('web3-laravel.confirmations_required', 6);

            return [
                'confirmed' => ($confs >= $required) && (is_array($effects) ? (data_get($effects, 'status.status') === 'success') : true),
                'confirmations' => $confs,
                'receipt' => $res,
                'blockNumber' => $checkpoint,
            ];
        } catch (\Throwable) {
            return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
        }
    }
}
