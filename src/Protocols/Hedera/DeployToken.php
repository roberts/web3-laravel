<?php

namespace Roberts\Web3Laravel\Protocols\Hedera;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class DeployToken
{
    /** Prepare meta for Hedera fungible token creation (HTS). */
    public static function prepare(Transaction $tx, Wallet $wallet): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['hedera'] = $meta['hedera'] ?? [];
        $tx->meta = $meta;
    }

    /**
     * Submit Hedera token creation (Phase 1 stub):
     * - Persist Contract with placeholder tokenId (e.g., 0.0.X)
     * - Persist Token meta
     * - Return synthetic transaction ID
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
        $sdkTokenId = null; $sdkTxId = null;
        try {
            /** @var \Roberts\Web3Laravel\Protocols\Hedera\HederaSdkInterface $sdk */
            $sdk = app(\Roberts\Web3Laravel\Protocols\Hedera\HederaSdkInterface::class);
            $res = $sdk->createFungibleToken($wallet, [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'initial_supply' => (string) ($tokenMeta['initial_supply'] ?? '0'),
                'recipient' => (string) data_get($meta, 'recipient.address'),
            ]);
            $sdkTokenId = (string) data_get($res, 'tokenId');
            $sdkTxId = (string) data_get($res, 'txId');
        } catch (\Throwable) {}

        // If a submit proxy is configured, call it; otherwise return stub values
        $submitUrl = (string) config('web3-laravel.hedera.submit_url', '');
        $tokenId = $sdkTokenId;
        $txId = $sdkTxId;
        if (! $tokenId && $submitUrl !== '') {
            try {
                $payload = [
                    'account' => $wallet->address,
                    'name' => $name,
                    'symbol' => $symbol,
                    'decimals' => $decimals,
                    'initial_supply' => (string) ($tokenMeta['initial_supply'] ?? '0'),
                    'recipient' => (string) data_get($meta, 'recipient.address'),
                ];
                $headers = [];
                $hdr = config('web3-laravel.hedera.auth_header');
                $tok = config('web3-laravel.hedera.auth_token');
                if ($hdr && $tok) {
                    $headers[$hdr] = $tok;
                }
                $res = \Illuminate\Support\Facades\Http::withHeaders($headers)->post($submitUrl, $payload)->json();
                $tokenId = (string) data_get($res, 'tokenId');
                $txId = (string) data_get($res, 'txId');
            } catch (\Throwable) {}
        }
        if (! $tokenId) {
            // Placeholder token id and synthetic tx id
            $tokenId = '0.0.'.hexdec(substr(hash('sha1', 'hedera:'.$tx->id), 0, 6));
        }
        if (! $txId) {
            $txId = $wallet->address.'@'.time();
        }

        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $tokenId],
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
            $tx->tx_hash = $txId;
            $tx->save();
        } catch (\Throwable) {
        }

        return $txId;
    }
}
