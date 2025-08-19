<?php

namespace Roberts\Web3Laravel\Protocols\Ton;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;

class TonProtocolAdapter implements ProtocolAdapter
{
    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::TON;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for TON ed25519');
        }
        // Minimal TON-like address placeholder: base64url of pubkey hash with "EQ" network tag
        $kp = \sodium_crypto_sign_keypair();
        $sk = \sodium_crypto_sign_secretkey($kp);
        $pk = \sodium_crypto_sign_publickey($kp);

        // Very simplified TON address placeholder: not a real workchain+bounceable encoding
        $hash = hash('sha256', $pk, true);
        $raw = "\x00".substr($hash, 0, 32); // 0 = workchain 0 placeholder
        $address = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $data = array_merge([
            'address' => $address,
            'key' => bin2hex($sk),
            'public_key' => bin2hex($pk),
            'key_scheme' => 'ed25519',
            'protocol' => BlockchainProtocol::TON,
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
        // Basic base64url length check (TON addresses are 48+ chars in urlsafe base64)
        return (bool) preg_match('/^[A-Za-z0-9_-]{40,}$/', $address);
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
}
