<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a cardano wallet with placeholder address and encrypted key', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::CARDANO,
        'native_symbol' => 'ADA',
        'native_decimals' => 6,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('cardano');
    expect($wallet->address)->toStartWith('addr1');

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
