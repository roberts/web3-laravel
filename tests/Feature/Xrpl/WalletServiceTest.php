<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates an xrpl wallet with derived address and encrypted key', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::XRPL,
        'native_symbol' => 'XRP',
        'native_decimals' => 6,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('xrpl');
    expect($wallet->address)->not->toBe('');

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
