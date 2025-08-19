<?php

use Roberts\Web3Laravel\Models\Wallet;

it('applies lowercase normalization for EVM address lookups', function () {
    $wallet = Wallet::factory()->create();
    $found = Wallet::byAddress(strtoupper($wallet->address))->first();
    expect($found?->id)->toBe($wallet->id);
});
