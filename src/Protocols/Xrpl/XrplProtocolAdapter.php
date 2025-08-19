<?php

namespace Roberts\Web3Laravel\Protocols\Xrpl;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;

class XrplProtocolAdapter implements ProtocolAdapter, ProtocolTransactionAdapter
{
    public function __construct(private KeyEngineInterface $keys, private XrplJsonRpcClient $rpc) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::XRPL;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for XRPL ed25519');
        }
        $scheme = data_get($attributes, 'key_scheme', 'ed25519');
        [$skHex, $pkBytes] = $this->keys->randomKeypair(BlockchainProtocol::XRPL, $scheme);
        $addr = $this->keys->publicKeyToAddress(BlockchainProtocol::XRPL, data_get($attributes, 'network', 'mainnet'), $scheme, $pkBytes);
        $data = array_merge([
            'address' => $addr,
            'key' => $skHex,
            'public_key' => bin2hex($pkBytes),
            'key_scheme' => $scheme,
            'protocol' => BlockchainProtocol::XRPL,
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
        throw new \RuntimeException('Not implemented');
    }

    public function normalizeAddress(string $address): string
    {
        return $address;
    }

    public function validateAddress(string $address): bool
    {
        return $address !== '';
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
        throw new \RuntimeException('Not implemented');
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
    // ProtocolTransactionAdapter (XRPL)
    // -----------------------------
    public function prepareTransaction(Transaction $tx, Wallet $wallet): void
    {
        // Best-effort: fetch account sequence and current open ledger fee
        $meta = (array) ($tx->meta ?? []);
        $meta['xrpl'] = $meta['xrpl'] ?? [];
        $meta['xrpl']['Account'] = $wallet->address;
        try {
            $info = $this->rpc->accountInfo($wallet->address);
            $seq = (int) data_get($info, 'account_data.Sequence', 0);
            if ($seq > 0) { $meta['xrpl']['Sequence'] = $seq; }
        } catch (\Throwable) {}
        try {
            $fee = $this->rpc->fee();
            $open = (string) (data_get($fee, 'drops.open_ledger_fee') ?? data_get($fee, 'drops.minimum_fee') ?? '12');
            $meta['xrpl']['Fee'] = $open; // in drops as string
        } catch (\Throwable) {}
        $tx->meta = $meta;
    }

    public function submitTransaction(Transaction $tx, Wallet $wallet): string
    {
        // Minimal path using XRPL server-side sign (only when configured). Safer client-side signing to be implemented later.
        $to = (string) $tx->to;
        $amount = (string) ($tx->value ?? ''); // drops
        if ($to === '' || $amount === '') {
            throw new \InvalidArgumentException('XRPL transaction requires destination and amount (drops)');
        }
        $xrpl = (array) ($tx->meta['xrpl'] ?? []);
        $sequence = $xrpl['Sequence'] ?? null;
        $fee = $xrpl['Fee'] ?? '12';
        // XRPL classic (XRP) Payment
        $payload = [
            'TransactionType' => 'Payment',
            'Account' => $wallet->address,
            'Destination' => $to,
            'Amount' => (string) $amount,
            'Fee' => (string) $fee,
        ];
        if ($sequence) { $payload['Sequence'] = (int) $sequence; }

        // Look for a server-side signing secret in wallet meta (NOT recommended for production)
        $secret = $wallet->meta['xrpl']['secret'] ?? null;
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('XRPL signing not configured; client-side signing not implemented yet');
        }
        $res = $this->rpc->sign($payload, ['secret' => $secret]);
        $txBlob = (string) data_get($res, 'tx_blob');
        if ($txBlob === '') {
            throw new \RuntimeException('XRPL sign failed');
        }
        $submit = $this->rpc->submit($txBlob);
        $engine = (string) (data_get($submit, 'engine_result') ?? '');
        $hash = (string) (data_get($submit, 'tx_json.hash') ?? '');
        if ($hash === '') {
            throw new \RuntimeException('XRPL submit failed: '.$engine);
        }

        return $hash;
    }

    public function checkConfirmations(Transaction $tx, Wallet $wallet): array
    {
        $hash = (string) $tx->tx_hash;
        if ($hash === '') {
            return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
        }
        try {
            $res = $this->rpc->tx($hash);
            if (!$res) {
                return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
            }
            $validated = (bool) ($res['validated'] ?? false);
            $ledgerIndex = isset($res['ledger_index']) ? (int) $res['ledger_index'] : null;
            $current = $this->rpc->ledgerCurrent();
            $confs = ($validated && $ledgerIndex) ? max(0, $current - $ledgerIndex + 1) : 0;
            $required = (int) config('web3-laravel.confirmations_required', 6);

            return [
                'confirmed' => $validated && $confs >= $required,
                'confirmations' => $confs,
                'receipt' => $res,
                'blockNumber' => $ledgerIndex,
            ];
        } catch (\Throwable) {
            return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
        }
    }
}
