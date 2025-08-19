<?php

namespace Roberts\Web3Laravel\Protocols\Hedera;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;

class HederaProtocolAdapter implements ProtocolAdapter
{
    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::HEDERA;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Hedera ed25519');
        }
        $kp = \sodium_crypto_sign_keypair();
        $sk = \sodium_crypto_sign_secretkey($kp);
        $pk = \sodium_crypto_sign_publickey($kp);
        // Placeholder account ID style: 0.0.X for local staging (not on-chain)
        $address = '0.0.'.hexdec(substr(bin2hex($pk), 0, 6));
        $data = array_merge([
            'address' => $address,
            'key' => bin2hex($sk),
            'protocol' => BlockchainProtocol::HEDERA,
            'is_active' => true,
            'meta' => ['account_status' => 'local_only'],
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
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', $address);
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
