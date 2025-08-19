<?php

namespace Roberts\Web3Laravel\Protocols\Bitcoin;

use Elliptic\EC;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;

class BitcoinProtocolAdapter implements ProtocolAdapter, ProtocolTransactionAdapter
{
    public function __construct(private KeyEngineInterface $keys) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::BITCOIN;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        // Phase 2: random secp256k1 keypair and Bech32 P2WPKH address
        [$priv, $pub] = $this->keys->randomKeypair(BlockchainProtocol::BITCOIN, 'secp256k1');
        // Derive compressed public key if not provided
        if ($pub === '') {
            $ec = new EC('secp256k1');
            $key = $ec->keyFromPrivate(ltrim($priv, '0x'), 'hex');
            $pubPoint = $key->getPublic();
            $xHex = str_pad($pubPoint->getX()->toString(16), 64, '0', STR_PAD_LEFT);
            $prefix = $pubPoint->getY()->isOdd() ? '03' : '02';
            $pub = hex2bin($prefix.$xHex);
        } else {
            // Ensure compressed form if 65-byte uncompressed
            if (strlen($pub) === 65 && $pub[0] === "\x04") {
                $x = substr($pub, 1, 32);
                $y = substr($pub, 33, 32);
                $yIsOdd = (ord($y[31]) & 1) === 1;
                $prefix = $yIsOdd ? '03' : '02';
                $pub = hex2bin($prefix.bin2hex($x));
            }
        }
        $network = (string) ($attributes['network'] ?? 'mainnet');
        $address = app(\Roberts\Web3Laravel\Services\Keys\KeyEngineInterface::class)
            ->publicKeyToAddress(BlockchainProtocol::BITCOIN, $network, 'secp256k1', $pub);
        $data = array_merge([
            'address' => $address,
            'key' => $priv,
            'public_key' => bin2hex($pub),
            'key_scheme' => 'secp256k1',
            'protocol' => BlockchainProtocol::BITCOIN,
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
        throw new \RuntimeException('Not implemented');
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        throw new \RuntimeException('Not implemented');
    }

    public function normalizeAddress(string $address): string
    {
        return strtolower($address);
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
    // ProtocolTransactionAdapter (Bitcoin)
    // -----------------------------
    public function prepareTransaction(Transaction $tx, Wallet $wallet): void
    {
        // No-op: UTXO selection/signing TBD; store target amount/address in meta
        $meta = (array) ($tx->meta ?? []);
        $meta['bitcoin'] = [
            'to' => $tx->to,
            'amount' => $tx->value,
        ];
        $tx->meta = $meta;
    }

    public function submitTransaction(Transaction $tx, Wallet $wallet): string
    {
        throw new \RuntimeException('Bitcoin transaction submission not implemented yet');
    }

    public function checkConfirmations(Transaction $tx, Wallet $wallet): array
    {
        return ['confirmed' => false, 'confirmations' => 0, 'receipt' => null, 'blockNumber' => null];
    }
}
