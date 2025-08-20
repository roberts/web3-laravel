<?php

namespace Roberts\Web3Laravel\Protocols\Ton;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class DeployToken
{
    /** Prepare meta for TON Jetton deployment (stub). */
    public static function prepare(Transaction $tx, Wallet $wallet): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['ton'] = $meta['ton'] ?? [];
        $meta['ton']['deployer'] = $wallet->address;
        $tx->meta = $meta;
    }

    /** Submit TON Jetton deployment.
     * If TON rpc_base is configured, attempts a Toncenter-compatible submit using sendBoc; otherwise uses stub persistence.
     */
    public static function submit(Transaction $tx, Wallet $wallet): string
    {
        $meta = (array) ($tx->meta ?? []);
        $tokenMeta = (array) ($meta['token'] ?? []);
        $name = (string) ($tokenMeta['name'] ?? '');
        $symbol = (string) ($tokenMeta['symbol'] ?? '');
        $decimals = (int) ($tokenMeta['decimals'] ?? 9);
        if ($symbol === '') {
            throw new \InvalidArgumentException('TON token symbol is required');
        }
        // Synthesize jetton master address placeholder deterministically from tx id (fallback)
        $master = 'jetton:'.substr(hash('sha256', 'ton:'.$tx->id.':'.$symbol), 0, 40);
        $hash = null;
        // Prefer SDK if bound, else attempt sendBoc if configured with provided BOC, else stub
        try {
            /** @var TonSdkInterface $sdk */
            $sdk = app(TonSdkInterface::class);
            $res = $sdk->deployJetton($wallet, [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'initial_supply' => (string) ($tokenMeta['initial_supply'] ?? '0'),
                'recipient' => (string) data_get($meta, 'recipient.address'),
            ]);
            $maybeMaster = (string) data_get($res, 'master', '');
            if ($maybeMaster !== '') {
                $master = $maybeMaster;
            }
            $hash = (string) data_get($res, 'txHash');
        } catch (\Throwable) {
        }

        // Attempt real submit if rpc is configured and a signed BOC is available in meta
        $rpcBase = (string) config('web3-laravel.ton.rpc_base', '');
        $boc = (string) data_get($meta, 'ton.boc', ''); // base64-encoded signed deploy message
        if (! $hash && $rpcBase !== '' && $boc !== '') {
            try {
                /** @var TonJsonRpcClient $client */
                $client = app(TonJsonRpcClient::class);
                $res = $client->sendBoc($boc);
                // Toncenter returns { ok: true, result: { id, ... } } or similar; accept common shapes
                $hash = (string) (data_get($res, 'result', '') ?: data_get($res, 'id', '') ?: data_get($res, 'hash', ''));
                if ($hash === '') {
                    $hash = 'boc:'.substr(hash('sha256', $boc), 0, 16);
                }
                // If a real master address is precomputed and provided, use it; otherwise keep synthetic
                $providedMaster = (string) data_get($meta, 'ton.master', '');
                if ($providedMaster !== '') {
                    $master = $providedMaster;
                }
            } catch (\Throwable) {
            }
        }
        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $master],
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
                    'name' => $name !== '' ? $name : $symbol,
                    'decimals' => $decimals,
                    'total_supply' => null,
                    'metadata' => [
                        'standard' => 'jetton',
                    ],
                ]
            );
            if (! $tx->contract_id) {
                $tx->contract_id = $contract->id;
            }
        } catch (\Throwable) {
        }
        if (! $hash) {
            $hash = 'stub:'.substr(hash('sha256', $master.':'.microtime(true)), 0, 16);
        }
        try {
            $tx->tx_hash = $hash;
            $tx->save();
        } catch (\Throwable) {
        }

        return $hash;
    }
}
