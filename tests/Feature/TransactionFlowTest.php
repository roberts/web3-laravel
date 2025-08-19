<?php

use Roberts\Web3Laravel\Models\Transaction;

// Chain-agnostic: ensure transactions can be created and updated without protocol specifics
it('creates and updates a transaction record', function () {
    $tx = Transaction::factory()->create();

    $tx->update(['status' => $tx->status]); // no-op change

    expect($tx->id)->toBeInt();
});
