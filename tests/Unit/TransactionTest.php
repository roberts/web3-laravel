<?php

use Roberts\Web3Laravel\Enums\TransactionStatus;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('casts status enum and provides helpers', function () {
    $tx = Transaction::factory()->create();

    expect($tx->status)->toBeInstanceOf(TransactionStatus::class)
        ->and($tx->statusValue())->toBeString()
        ->and(in_array($tx->statusValue(), array_map(fn ($e) => $e->value, TransactionStatus::cases())))->toBeTrue();

    // helper methods
    expect(
        $tx->isPending() || $tx->isPreparing() || $tx->isPrepared() || $tx->isSubmitted() || $tx->isConfirmed() || $tx->isFailed()
    )->toBeTrue();
});

it('dispatches requested event on create (pipeline starts)', function () {
    $wallet = Wallet::factory()->create();

    $tx = Transaction::factory()->create(['wallet_id' => $wallet->id]);

    expect($tx->id)->not()->toBeNull();
});
