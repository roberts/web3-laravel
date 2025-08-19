<?php

namespace Roberts\Web3Laravel\Protocols\Evm;

use Elliptic\EC;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Support\Address;
use Roberts\Web3Laravel\Support\Hex;
use Roberts\Web3Laravel\Support\Keccak;

class EvmProtocolAdapter implements ProtocolAdapter
{
    public function __construct(private EvmClientInterface $evm) {}

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::EVM;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        $privHex = '0x'.str_pad($keyPair->getPrivate('hex'), 64, '0', STR_PAD_LEFT);

        $pub = $keyPair->getPublic(false, 'hex'); // 04 + x + y
        $pubNoPrefix = substr($pub, 2);
        $hash = Keccak::hash($pubNoPrefix, true);
        $address = '0x'.substr(Hex::stripZero($hash), -40);
        $address = strtolower($address);

        $data = array_merge([
            'address' => $address,
            'key' => $privHex, // Wallet mutator will encrypt
            'protocol' => BlockchainProtocol::EVM,
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
        return (string) $this->evm->getBalance($wallet->address, 'latest');
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        // Leave EVM transfer to TransactionService in current codebase; not implemented here yet.
        throw new \RuntimeException('transferNative not implemented via adapter for EVM');
    }

    public function normalizeAddress(string $address): string
    {
        return Address::normalize($address);
    }

    public function validateAddress(string $address): bool
    {
        return Address::isValidEvm($address);
    }

    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string
    {
        // Reuse existing ERC-20 path for EVM
        $svc = app(\Roberts\Web3Laravel\Services\TokenService::class);

        return $svc->balanceOf($token, $ownerAddress);
    }

    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string
    {
        $svc = app(\Roberts\Web3Laravel\Services\TokenService::class);

        return $svc->allowance($token, $ownerAddress, $spenderAddress);
    }

    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
    {
        // For EVM, token transfers are handled via TransactionService/TokenService to build ABI calls.
        throw new \RuntimeException('transferToken not implemented via adapter for EVM');
    }

    public function approveToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress, string $amount): string
    {
        // For EVM, approvals are handled via TransactionService/TokenService to build ABI calls.
        throw new \RuntimeException('approveToken not implemented via adapter for EVM');
    }

    public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress): string
    {
        // For EVM, approvals are handled via TransactionService/TokenService to build ABI calls (approve 0).
        throw new \RuntimeException('revokeToken not implemented via adapter for EVM');
    }
}
