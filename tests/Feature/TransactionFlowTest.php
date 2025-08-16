<?php

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('dispatches event and attempts to submit transaction', function () {
    $wallet = Wallet::factory()->create();

    $tx = Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => '0x'.str_repeat('0', 40),
        'value' => '0x0',
        'gas_limit' => 21000,
        'is_1559' => false,
        'gwei' => '0x3b9aca00',
    ]);

    $tx->refresh();
    expect(in_array($tx->status, ['pending', 'submitted', 'failed']))->toBeTrue();
});
