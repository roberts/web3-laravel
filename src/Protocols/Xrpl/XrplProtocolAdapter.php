<?php

namespace Roberts\Web3Laravel\Protocols\Xrpl;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;

class XrplProtocolAdapter implements ProtocolAdapter
{
    public function __construct(private KeyEngineInterface $keys)
    {
    }
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
}
