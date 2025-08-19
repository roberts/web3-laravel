<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a ton wallet with placeholder address and encrypted key', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
        return;
    }
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::TON,
        'native_symbol' => 'TON',
        'native_decimals' => 9,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('ton');
    // Base64url placeholder
    expect($wallet->address)->toMatch('/^[A-Za-z0-9_-]{40,}$/');

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
