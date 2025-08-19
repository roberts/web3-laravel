<?php

namespace Roberts\Web3Laravel\Protocols\Bitcoin;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;

class BitcoinProtocolAdapter implements ProtocolAdapter
{
    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::BITCOIN;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        // Phase 1: random secp256k1 keypair; address derivation TBD (P2WPKH/bech32)
        $priv = '0x'.strtolower(bin2hex(random_bytes(32)));
        $data = array_merge([
            'address' => 'bc1'.substr(bin2hex(random_bytes(20)), 0, 20), // placeholder
            'key' => $priv,
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
}
