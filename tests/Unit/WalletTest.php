<?php

use Roberts\Web3Laravel\Models\Wallet;

it('encrypts and decrypts private key transparently', function () {
    $plain = '0x'.strtolower(bin2hex(random_bytes(32)));
    $wallet = Wallet::factory()->create(['key' => $plain]);

    // Stored value should not equal plain
    expect($wallet->getAttributes()['key'])->not()->toBe($plain);

    // Decrypt helper returns original
    expect($wallet->decryptKey())->toBe($plain);

    // Masked key is safe
    expect($wallet->maskedKey())->toContain(substr($plain, 0, 6))
        ->and($wallet->maskedKey())->toContain(substr($plain, -4));
});

it('applies lowercase normalization for address lookups', function () {
    $wallet = Wallet::factory()->create();
    $found = Wallet::byAddress(strtoupper($wallet->address))->first();
    expect($found?->id)->toBe($wallet->id);
});
