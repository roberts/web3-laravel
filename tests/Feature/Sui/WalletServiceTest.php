<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a sui wallet with derived address and encrypted key', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::SUI,
        'native_symbol' => 'SUI',
        'native_decimals' => 9,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('sui');
    expect($wallet->address)->toStartWith('0x');
    expect(strlen($wallet->address))->toBe(66);

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
