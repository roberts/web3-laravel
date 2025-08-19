<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a hedera wallet with placeholder address and encrypted key', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::HEDERA,
        'native_symbol' => 'HBAR',
        'native_decimals' => 8,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('hedera');
    expect($wallet->address)->toMatch('/^\d+\.\d+\./');

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
