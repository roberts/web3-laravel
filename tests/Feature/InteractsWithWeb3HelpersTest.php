<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Protocols\Xrpl\XrplJsonRpcClient;

it('normalizes an EVM address via trait helper', function () {
    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::EVM]);
    $addr = $wallet->normalizeAddress('0xAAbBCCddeEFF00112233445566778899AaBbCcDd');
    expect($addr)->toBe('0xaabbccddeeff00112233445566778899aabbccdd');
});

it('validates XRPL address via trait helper (non-empty)', function () {
    $wallet = Wallet::factory()->create([
        'protocol' => BlockchainProtocol::XRPL,
        'address' => 'rTEST',
    ]);
    expect($wallet->validateAddress('rSAMPLETEST'))
        ->toBeTrue();
});

it('sequence() returns EVM hex nonce via trait', function () {
    // Fake EVM client
    app()->bind(EvmClientInterface::class, function () {
        return new class implements EvmClientInterface {
            public function getBalance(string $address, string $blockTag = 'latest'): string { return '0x0'; }
            public function gasPrice(): string { return '0x1'; }
            public function maxPriorityFeePerGas(): string { return '0x1'; }
            public function estimateGas(array $tx, string $blockTag = 'latest'): string { return '0x5208'; }
            public function getTransactionCount(string $address, string $blockTag = 'latest'): string { return '0x7'; }
            public function sendRawTransaction(string $raw): string { return '0xhash'; }
            public function blockNumber(): string { return '0x1'; }
            public function getTransactionReceipt(string $hash): ?array { return null; }
        };
    });

    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::EVM]);
    expect($wallet->sequence())->toBe('0x7');
});

it('sequence() returns XRPL integer via trait', function () {
    app()->bind(XrplJsonRpcClient::class, function () {
        return new class {
            public function accountInfo(string $address): array { return ['account_data' => ['Sequence' => 9, 'Balance' => '0']]; }
        };
    });
    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::XRPL, 'address' => 'rTEST']);
    expect($wallet->sequence())->toBe(9);
});

it('transferNative uses EVM adapter and returns tx hash', function () {
    // Fake EVM client for raw send path
    app()->bind(EvmClientInterface::class, function () {
        return new class implements EvmClientInterface {
            public function getBalance(string $address, string $blockTag = 'latest'): string { return '0x0'; }
            public function gasPrice(): string { return '0x1'; }
            public function maxPriorityFeePerGas(): string { return '0x1'; }
            public function estimateGas(array $tx, string $blockTag = 'latest'): string { return '0x5208'; }
            public function getTransactionCount(string $address, string $blockTag = 'latest'): string { return '0x1'; }
            public function sendRawTransaction(string $raw): string { return '0xDEADBEEF'; }
            public function blockNumber(): string { return '0x1'; }
            public function getTransactionReceipt(string $hash): ?array { return null; }
        };
    });

    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::EVM])->fresh();
    $hash = $wallet->transferNative('0x1111111111111111111111111111111111111111', '1');
    expect($hash)->toBe('0xDEADBEEF');
});
