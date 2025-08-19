<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers and resolves adapters by protocol', function () {
    $router = new ProtocolRouter;

    $evm = new class implements ProtocolAdapter
    {
        public function protocol(): BlockchainProtocol
        {
            return BlockchainProtocol::EVM;
        }

        public function createWallet(array $attributes = [], ?\Illuminate\Database\Eloquent\Model $owner = null, ?\Roberts\Web3Laravel\Models\Blockchain $blockchain = null): \Roberts\Web3Laravel\Models\Wallet
        {
            throw new RuntimeException('noop');
        }

        public function getNativeBalance(\Roberts\Web3Laravel\Models\Wallet $wallet): string
        {
            return '1';
        }

        public function transferNative(\Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
        {
            return '0x';
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

        public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
        {
            return '0x';
        }

        public function approveToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress, string $amount): string
        {
            return '0x';
        }

        public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress): string
        {
            return '0x';
        }
    };

    $sol = new class implements ProtocolAdapter
    {
        public function protocol(): BlockchainProtocol
        {
            return BlockchainProtocol::SOLANA;
        }

        public function createWallet(array $attributes = [], ?\Illuminate\Database\Eloquent\Model $owner = null, ?\Roberts\Web3Laravel\Models\Blockchain $blockchain = null): \Roberts\Web3Laravel\Models\Wallet
        {
            throw new RuntimeException('noop');
        }

        public function getNativeBalance(\Roberts\Web3Laravel\Models\Wallet $wallet): string
        {
            return '2';
        }

        public function transferNative(\Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
        {
            return 'sig';
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

        public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string
        {
            return 'sig';
        }

        public function approveToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress, string $amount): string
        {
            return 'sig';
        }

        public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress): string
        {
            return 'sig';
        }
    };

    $router->register($evm);
    $router->register($sol);

    expect($router->for(BlockchainProtocol::EVM))->toBeInstanceOf(ProtocolAdapter::class)
        ->and($router->for(BlockchainProtocol::SOLANA))->toBeInstanceOf(ProtocolAdapter::class);
});

it('throws when adapter not registered', function () {
    $router = new ProtocolRouter;
    $router->for(BlockchainProtocol::EVM);
})->throws(InvalidArgumentException::class);
