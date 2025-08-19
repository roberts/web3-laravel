<?php

use Roberts\Web3Laravel\Models\Wallet;

// Chain-agnostic root: ensure we can create a wallet without protocol assumptions
it('creates a wallet record with required defaults', function () {
    $wallet = Wallet::factory()->create();

    expect($wallet->id)->toBeInt()
        ->and($wallet->address)->toBeString()
        ->and($wallet->is_active)->toBeTrue();
});
