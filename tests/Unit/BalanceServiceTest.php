<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Services\BalanceService;

it('routes native and token balance calls to the correct adapter', function () {
    $router = new ProtocolRouter;

    $fake = new class implements ProtocolAdapter
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
            return '123';
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
            return true;
        }

        public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string
        {
            return '456';
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

    $router->register($fake);

    $svc = new BalanceService($router);
    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::EVM]);
    $token = Token::factory()->create();

    expect($svc->native($wallet))->toBe('123')
        ->and($svc->token($token, $wallet))->toBe('456');
});
