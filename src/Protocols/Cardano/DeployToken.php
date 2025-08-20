<?php

namespace Roberts\Web3Laravel\Protocols\Cardano;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class DeployToken
{
    /** Prepare meta for Cardano fungible token creation (native asset). */
    public static function prepare(Transaction $tx, Wallet $wallet): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['cardano'] = $meta['cardano'] ?? [];
        $tx->meta = $meta;
    }

    /**
     * Submit Cardano token creation (Phase 1 stub):
     * - Persist Contract with placeholder policy::asset
     * - Persist Token meta
     * - Return synthetic tx hash
     */
    public static function submit(Transaction $tx, Wallet $wallet): string
    {
        $meta = (array) ($tx->meta ?? []);
        $tokenMeta = (array) ($meta['token'] ?? []);
        $name = (string) ($tokenMeta['name'] ?? '');
        $symbol = (string) ($tokenMeta['symbol'] ?? '');
        $decimals = (int) ($tokenMeta['decimals'] ?? 0);
        if ($name === '' || $symbol === '') {
            throw new \InvalidArgumentException('Token name and symbol are required');
        }

        // Prefer SDK if bound, then optional HTTP proxy, else stub
        $sdkAssetId = null; $sdkTxHash = null;
        try {
            /** @var \Roberts\Web3Laravel\Protocols\Cardano\CardanoSdkInterface $sdk */
            $sdk = app(\Roberts\Web3Laravel\Protocols\Cardano\CardanoSdkInterface::class);
            $res = $sdk->mintNativeAsset($wallet, [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'initial_supply' => (string) ($tokenMeta['initial_supply'] ?? '0'),
                'recipient' => (string) data_get($meta, 'recipient.address'),
            ]);
            $sdkAssetId = (string) data_get($res, 'assetId');
            $sdkTxHash = (string) data_get($res, 'txHash');
        } catch (\Throwable) {}

        // If a submit proxy is configured, call it; otherwise use stub values
        $submitUrl = (string) config('web3-laravel.cardano.submit_url', '');
        $assetId = $sdkAssetId;
        $hash = $sdkTxHash;
        if (! $assetId && $submitUrl !== '') {
            try {
                $payload = [
                    'address' => $wallet->address,
                    'name' => $name,
                    'symbol' => $symbol,
                    'decimals' => $decimals,
                    'initial_supply' => (string) ($tokenMeta['initial_supply'] ?? '0'),
                    'recipient' => (string) data_get($meta, 'recipient.address'),
                ];
                $headers = [];
                $hdr = config('web3-laravel.cardano.auth_header');
                $tok = config('web3-laravel.cardano.auth_token');
                if ($hdr && $tok) {
                    $headers[$hdr] = $tok;
                }
                $res = \Illuminate\Support\Facades\Http::withHeaders($headers)->post($submitUrl, $payload)->json();
                $assetId = (string) data_get($res, 'assetId');
                $hash = (string) data_get($res, 'txHash');
            } catch (\Throwable) {}
        }
        if (! $assetId) {
            // Placeholder policy and asset (hex)
            $policyId = substr(hash('sha256', 'cardano:policy:'.$tx->id), 0, 56);
            $assetNameHex = bin2hex($symbol);
            $assetId = $policyId.'.'.$assetNameHex;
        }
        if (! $hash) {
            $hash = '0x'.substr(hash('sha256', 'cardano:create:'.$tx->id.':'.microtime(true)), 0, 64);
        }

        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $assetId],
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
            $tx->tx_hash = $hash;
            $tx->save();
        } catch (\Throwable) {
        }

        return $hash;
    }
}
