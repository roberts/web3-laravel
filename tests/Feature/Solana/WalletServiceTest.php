<?php

use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a solana wallet with base58 address and encrypted key', function () {
    $chain = Blockchain::factory()->create([
        'protocol' => 'solana',
        'native_symbol' => 'SOL',
        'native_decimals' => 9,
    ]);
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('solana');
    expect(strlen($wallet->address))->toBeGreaterThan(30);

    // key is stored encrypted and can be decrypted
    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
