<?php

namespace Roberts\Web3Laravel\Protocols\Sui;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Tuupola\Base58; // not used but kept for parity

class SuiProtocolAdapter implements ProtocolAdapter
{
    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::SUI;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for Sui ed25519');
        }
        $kp = \sodium_crypto_sign_keypair();
        $sk = \sodium_crypto_sign_secretkey($kp);
        $pk = \sodium_crypto_sign_publickey($kp);
        $address = '0x'.bin2hex($pk);
        $data = array_merge([
            'address' => $address,
            'key' => bin2hex($sk),
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

    public function getNativeBalance(Wallet $wallet): string { return '0'; }
    public function transferNative(Wallet $from, string $toAddress, string $amount): string { throw new \RuntimeException('Not implemented'); }
    public function normalizeAddress(string $address): string { return strtolower($address); }
    public function validateAddress(string $address): bool { return (bool) preg_match('/^0x[0-9a-f]{64}$/', strtolower($address)); }
    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string { return '0'; }
    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string { return '0'; }
    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $from, string $toAddress, string $amount): string { throw new \RuntimeException('Not implemented'); }
    public function approveToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $owner, string $spenderAddress, string $amount): string { throw new \RuntimeException('Not implemented'); }
    public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, Wallet $owner, string $spenderAddress): string { throw new \RuntimeException('Not implemented'); }
}
