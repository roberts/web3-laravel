<?php

namespace Roberts\Web3Laravel\Protocols\Sui;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;
use Illuminate\Support\Facades\Crypt;

class DeployToken
{
    /** Prepare meta for Sui fungible token creation (placeholders for package/policy). */
    public static function prepare(Transaction $tx, Wallet $wallet, SuiJsonRpcClient $rpc): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['sui'] = $meta['sui'] ?? [];
        // Best-effort: store reference gas price for later budgeting
        try {
            $meta['sui']['referenceGasPrice'] = $rpc->getReferenceGasPrice();
        } catch (\Throwable) {
        }
        // Surface coin factory config into tx meta for transparency/debugging
        $factory = (array) (config('web3-laravel.sui.coin_factory') ?? []);
        $meta['sui']['factory'] = [
            'package' => $factory['package'] ?? null,
            'module' => $factory['module'] ?? 'factory',
            'function' => $factory['function'] ?? 'create',
            'mint_after_create' => (bool) ($factory['mint_after_create'] ?? true),
        ];
        $tx->meta = $meta;
    }

    /**
     * Submit Sui token creation via a Coin Factory when configured.
     * Fallback to a persistence-only stub if no factory is configured.
     */
    public static function submit(Transaction $tx, Wallet $wallet, SuiJsonRpcClient $rpc, KeyEngineInterface $keys): string
    {
        $meta = (array) ($tx->meta ?? []);
        $tokenMeta = (array) ($meta['token'] ?? []);
        $name = (string) ($tokenMeta['name'] ?? '');
        $symbol = (string) ($tokenMeta['symbol'] ?? '');
        $decimals = (int) ($tokenMeta['decimals'] ?? 0);
        $initial = (string) ($tokenMeta['initial_supply'] ?? '0');
        $recipient = (string) (($meta['recipient'] ?? $wallet->address) ?: $wallet->address);
        if ($name === '' || $symbol === '') {
            throw new \InvalidArgumentException('Token name and symbol are required');
        }

        $factory = (array) (config('web3-laravel.sui.coin_factory') ?? []);
        $pkg = (string) ($factory['package'] ?? '');
        if ($pkg === '') {
            // No factory: fallback to stub persistence only
            return self::persistStub($tx, $wallet, $name, $symbol, $decimals);
        }
        $module = (string) ($factory['module'] ?? 'factory');
        $function = (string) ($factory['function'] ?? 'create');
        $mintAfter = (bool) ($factory['mint_after_create'] ?? true);

        // Estimate gas budget
        $gasPrice = (int) ($meta['sui']['referenceGasPrice'] ?? 0);
        if ($gasPrice <= 0) {
            try { $gasPrice = $rpc->getReferenceGasPrice(); } catch (\Throwable) { $gasPrice = 10; }
        }
        $mult = (int) (config('web3-laravel.sui.gas_budget_multiplier', 10));
        $min = (int) (config('web3-laravel.sui.min_gas_budget', 1000));
        $gasBudget = max($min, $gasPrice * max(1, $mult));

        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Sui signing');
        }
        // Build moveCall for factory::create(name, symbol, decimals)
        $build = $rpc->moveCall(
            $wallet->address,
            $pkg,
            $module,
            $function,
            [],
            [ $name, strtoupper($symbol), (int) $decimals ],
            (int) $gasBudget,
        );
        $txBytes = (string) (data_get($build, 'txBytes') ?? '');
        if ($txBytes === '') {
            throw new \RuntimeException('Sui moveCall (coin factory create) did not return txBytes');
        }

        // Sign and execute
        $secretHex = Crypt::decryptString($wallet->key);
        $secret = hex2bin($secretHex);
        if ($secret === false) {
            throw new \RuntimeException('Invalid Sui secret key encoding');
        }
        $sig = sodium_crypto_sign_detached(base64_decode($txBytes), $secret);
        $sigBase64 = base64_encode("\x00".$sig); // ed25519 flag
        $res = $rpc->executeTransactionBlock($txBytes, [$sigBase64]);
        $digest = (string) (data_get($res, 'digest') ?? '');
        if ($digest === '') {
            throw new \RuntimeException('Sui executeTransactionBlock failed');
        }

        // Parse result to get coin type and TreasuryCap id if returned via events/changes
        $coinType = null;
        $treasuryCapId = null;
        $effects = data_get($res, 'effects') ?: [];
        $objectChanges = data_get($effects, 'created') ?: [];
        if (empty($objectChanges)) {
            // try top-level objectChanges if provided
            $objectChanges = (array) (data_get($res, 'objectChanges') ?: []);
        }
        foreach ($objectChanges as $obj) {
            $type = (string) (data_get($obj, 'type') ?? (data_get($obj, 'objectType') ?? ''));
            $objType = (string) (data_get($obj, 'objectType') ?? '');
            $objId = (string) (data_get($obj, 'objectId') ?? (data_get($obj, 'reference.objectId') ?? ''));
            $typeStr = $objType ?: $type;
            if ($typeStr !== '' && str_contains($typeStr, 'TreasuryCap<')) {
                // Extract coin type from TreasuryCap<...>
                $start = strpos($typeStr, '<');
                $end = strrpos($typeStr, '>');
                if ($start !== false && $end !== false && $end > $start) {
                    $coinType = substr($typeStr, $start + 1, $end - $start - 1);
                    $treasuryCapId = $objId ?: $treasuryCapId;
                }
            } elseif ($typeStr !== '' && preg_match('/::coin::TreasuryCap<(.+)>/i', $typeStr, $m)) {
                $coinType = $m[1];
                $treasuryCapId = $objId ?: $treasuryCapId;
            }
        }

        // Persist contract/token models
        if ($coinType === null) {
            // As a fallback, synthesize a type from package and symbol
            $coinType = sprintf('%s::%s::%s', $pkg, $module, strtoupper($symbol));
        }
        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $coinType],
                [
                    'blockchain_id' => $tx->blockchain_id,
                    'creator' => $wallet->address,
                    'abi' => null,
                ]
            );
            Web3Token::query()->firstOrCreate(
                ['contract_id' => $contract->id],
                [
                    'symbol' => $symbol,
                    'name' => $name,
                    'decimals' => $decimals,
                    'total_supply' => '0',
                ]
            );
            if (! $tx->contract_id) {
                $tx->contract_id = $contract->id;
            }
            $meta['sui'] = $meta['sui'] ?? [];
            $meta['sui']['coin_type'] = $coinType;
            if ($treasuryCapId) {
                $meta['sui']['treasury_cap_id'] = $treasuryCapId;
            }
            $tx->meta = $meta;
            $tx->tx_hash = $digest;
            $tx->save();
        } catch (\Throwable) {
        }

        // Optional initial mint and transfer to recipient if TreasuryCap available
        try {
            if ($mintAfter && $treasuryCapId && (string) $initial !== '0') {
                // Build mint: factory::mint<T>(treasury_cap, amount)
                $mintBuild = $rpc->moveCall(
                    $wallet->address,
                    $pkg,
                    $module,
                    'mint',
                    [ $coinType ],
                    [ $treasuryCapId, (int) $initial ],
                    (int) $gasBudget,
                );
                $mintTx = (string) (data_get($mintBuild, 'txBytes') ?? '');
                if ($mintTx !== '') {
                    $sig2 = sodium_crypto_sign_detached(base64_decode($mintTx), $secret);
                    $sig2b = base64_encode("\x00".$sig2);
                    $rpc->executeTransactionBlock($mintTx, [$sig2b]);
                }
            }
        } catch (\Throwable) {
            // ignore optional mint errors
        }

        // Attempt a transfer of a freshly minted coin to recipient; best-effort
        // NOTE: Full split/merge selection is omitted here; factories often mint into a new coin object owned by signer.
        try {
            if ((string) $initial !== '0') {
                $coins = $rpc->getCoins($wallet->address, $coinType)['data'] ?? [];
                if (! empty($coins)) {
                    $coinId = (string) ($coins[0]['coinObjectId'] ?? '');
                    if ($coinId !== '' && strtolower($recipient) !== strtolower($wallet->address)) {
                        $xferBuild = $rpc->transferObject($wallet->address, $coinId, $recipient, (int) $gasBudget);
                        $xBytes = (string) (data_get($xferBuild, 'txBytes') ?? '');
                        if ($xBytes !== '') {
                            $sig3 = sodium_crypto_sign_detached(base64_decode($xBytes), $secret);
                            $sig3b = base64_encode("\x00".$sig3);
                            $rpc->executeTransactionBlock($xBytes, [$sig3b]);
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }

        return $digest;
    }

    /** Persist Contract/Token only with a synthetic type; return synthetic digest. */
    private static function persistStub(Transaction $tx, Wallet $wallet, string $name, string $symbol, int $decimals): string
    {
        $pkg = substr(hash('sha256', 'sui:pkg:'.$tx->id), 0, 64);
        $coinType = sprintf('0x%s::token::%s', $pkg, strtoupper($symbol));
        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $coinType],
                [
                    'blockchain_id' => $tx->blockchain_id,
                    'creator' => $wallet->address,
                    'abi' => null,
                ]
            );
            Web3Token::query()->firstOrCreate(
                ['contract_id' => $contract->id],
                [
                    'symbol' => $symbol,
                    'name' => $name,
                    'decimals' => $decimals,
                    'total_supply' => '0',
                ]
            );
            if (! $tx->contract_id) {
                $tx->contract_id = $contract->id;
            }
        } catch (\Throwable) {
        }
        $digest = '0x'.substr(hash('sha256', 'sui:create:'.$tx->id.':'.microtime(true)), 0, 64);
        try {
            $tx->tx_hash = $digest;
            $tx->save();
        } catch (\Throwable) {
        }
        return $digest;
    }
}
