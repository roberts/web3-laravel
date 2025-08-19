<?php

use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a wallet with encrypted key and derived address', function () {
    $chain = Blockchain::factory()->create();
    $service = new WalletService;

    $wallet = $service->create([], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('evm');
    expect($wallet->address)->toStartWith('0x');
    expect(strlen($wallet->address))->toBe(42);

    // key is stored encrypted and can be decrypted
    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
    expect(strlen((string) $plain))->toBeGreaterThan(2);

    // Ensure mutator doesn't double-encrypt
    $wallet->key = $wallet->key; // set encrypted again
    $wallet->save();
    expect($wallet->decryptKey())->toBe($plain);
});
