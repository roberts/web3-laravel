<?php

namespace Roberts\Web3Laravel\Protocols\Xrpl;

use Roberts\Web3Laravel\Models\Contract as Web3Contract;
use Roberts\Web3Laravel\Models\Token as Web3Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class DeployToken
{
    /** Prepare XRPL token issuance meta. */
    public static function prepare(Transaction $tx, Wallet $wallet, XrplJsonRpcClient $rpc): void
    {
        $meta = (array) ($tx->meta ?? []);
        $meta['xrpl'] = $meta['xrpl'] ?? [];
        $meta['xrpl']['Account'] = $wallet->address;
        $meta['xrpl']['sign_mode'] = config('web3-laravel.xrpl.sign_mode', 'server');
        // Currency: 3-letter code or 160-bit hex per XRPL rules; we derive from symbol by default
        $token = (array) ($meta['token'] ?? []);
        $symbol = strtoupper((string) ($token['symbol'] ?? ''));
        if ($symbol !== '' && strlen($symbol) <= 3) {
            $meta['xrpl']['Currency'] = $symbol; // ISO-like short code
        } elseif ($symbol !== '') {
            // Hex-encode up to 20 bytes for long code
            $hex = bin2hex(substr($symbol, 0, 20));
            $meta['xrpl']['Currency'] = strtoupper($hex);
        }
        try {
            $info = $rpc->accountInfo($wallet->address);
            $seq = (int) data_get($info, 'account_data.Sequence', 0);
            if ($seq > 0) {
                $meta['xrpl']['Sequence'] = $seq;
            }
        } catch (\Throwable) {
        }
        try {
            $fee = $rpc->fee();
            $open = (string) (data_get($fee, 'drops.open_ledger_fee') ?? data_get($fee, 'drops.minimum_fee') ?? '12');
            $meta['xrpl']['Fee'] = $open; // in drops
        } catch (\Throwable) {
        }

        $tx->meta = $meta;
    }

    /**
     * Submit minimal XRPL token issuance flow (Phase 1 pragmatic):
     * - Assumes server-side signing available (wallet->meta['xrpl']['secret'])
     * - Persists Contract (issuer+currency) and Token
     * - Creates an initial Payment to recipient if provided
     */
    public static function submit(Transaction $tx, Wallet $wallet, XrplJsonRpcClient $rpc): string
    {
        $meta = (array) ($tx->meta ?? []);
        $tokenMeta = (array) ($meta['token'] ?? []);
        $name = (string) ($tokenMeta['name'] ?? '');
        $symbol = strtoupper((string) ($tokenMeta['symbol'] ?? ''));
        $decimals = (int) ($tokenMeta['decimals'] ?? 0);
        $initial = (string) ($tokenMeta['initial_supply'] ?? '0');
        if ($symbol === '') {
            throw new \InvalidArgumentException('XRPL token symbol is required');
        }
        $xrpl = (array) ($meta['xrpl'] ?? []);
        $currency = (string) ($xrpl['Currency'] ?? $symbol);
        $issuer = $wallet->address;
        $sequence = $xrpl['Sequence'] ?? null;
        $fee = (string) ($xrpl['Fee'] ?? '12');

        // 1) Ensure account flags if needed (optional in Phase 1). Skipped for brevity.

        // 2) Persist models using issuer+currency as unique identifier
        $contractAddr = $issuer.':'.$currency; // canonical XRPL IOU identifier
        try {
            $contract = Web3Contract::query()->firstOrCreate(
                ['address' => $contractAddr],
                [
                    'blockchain_id' => $tx->blockchain_id,
                    'creator' => $issuer,
                    'abi' => null,
                ]
            );
            Web3Token::query()->firstOrCreate(
                ['contract_id' => $contract->id],
                [
                    'symbol' => $symbol,
                    'name' => $name !== '' ? $name : $symbol,
                    'decimals' => $decimals,
                    'total_supply' => null, // XRPL IOU supply is dynamic
                    'metadata' => [
                        'issuer' => $issuer,
                        'currency' => $currency,
                        'standard' => 'xrpl',
                    ],
                ]
            );
            if (! $tx->contract_id) {
                $tx->contract_id = $contract->id;
            }
        } catch (\Throwable) {
        }

        // 3) Initial distribution (optional): ensure trustline then send IOU Payment to recipient
        $recipientAddress = (string) (data_get($meta, 'recipient.address') ?? '');
        $hash = '';
        if ($recipientAddress !== '' && (string) $initial !== '0') {
            // Auto TrustSet if configured and we manage the recipient wallet
            $autoTrust = (bool) config('web3-laravel.xrpl.auto_trustline', false);
            $recipientWalletId = data_get($meta, 'recipient.wallet_id');
            if ($autoTrust && $recipientWalletId) {
                try {
                    /** @var \Roberts\Web3Laravel\Models\Wallet|null $recipientWallet */
                    $recipientWallet = \Roberts\Web3Laravel\Models\Wallet::find((int) $recipientWalletId);
                    $secretRec = $recipientWallet?->meta['xrpl']['secret'] ?? null;
                    if ($recipientWallet && is_string($secretRec) && $secretRec !== '') {
                        // Build TrustSet transaction from recipient to issuer/currency
                        $limit = [
                            'currency' => $currency,
                            'issuer' => $issuer,
                            'value' => '1000000000000000000', // very high default limit; adjust per policy
                        ];
                        $trust = [
                            'TransactionType' => 'TrustSet',
                            'Account' => $recipientWallet->address,
                            'LimitAmount' => $limit,
                            'Fee' => $fee,
                        ];
                        // Sign as recipient using server-side signing
                        $signed = $rpc->sign($trust, ['secret' => $secretRec]);
                        $blob = (string) data_get($signed, 'tx_blob');
                        if ($blob !== '') {
                            $rpc->submit($blob);
                        }
                    }
                } catch (\Throwable) {
                }
            }
            $amountObj = [
                'currency' => $currency,
                'issuer' => $issuer,
                'value' => (string) $initial, // value is decimal string on XRPL
            ];
            $payload = [
                'TransactionType' => 'Payment',
                'Account' => $issuer,
                'Destination' => $recipientAddress,
                'Amount' => $amountObj,
                'Fee' => $fee,
            ];
            if ($sequence) {
                $payload['Sequence'] = (int) $sequence;
            }
            $secret = $wallet->meta['xrpl']['secret'] ?? null;
            if (! is_string($secret) || $secret === '') {
                // Without server sign, skip on-chain submit in Phase 1
                $hash = 'stub:'.substr(hash('sha256', $contractAddr.':'.microtime(true)), 0, 16);
            } else {
                $res = $rpc->sign($payload, ['secret' => $secret]);
                $txBlob = (string) data_get($res, 'tx_blob');
                if ($txBlob !== '') {
                    $submit = $rpc->submit($txBlob);
                    $hash = (string) (data_get($submit, 'tx_json.hash') ?? '');
                }
            }
        } else {
            // No initial transfer; return synthetic tx id for bookkeeping
            $hash = 'stub:'.substr(hash('sha256', $contractAddr.':'.microtime(true)), 0, 16);
        }

        try {
            $tx->tx_hash = $hash;
            $tx->meta = array_merge($meta, ['xrpl' => array_merge($xrpl, ['issuer' => $issuer, 'currency' => $currency])]);
            $tx->save();
        } catch (\Throwable) {
        }

        return $hash;
    }
}
