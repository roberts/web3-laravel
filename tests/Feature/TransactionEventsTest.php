<?php

use Roberts\Web3Laravel\Models\Transaction;

// Protocol-agnostic: creating a transaction works; detailed flows tested per protocol.
it('can create a transaction model', function () {
    $tx = Transaction::factory()->create();
    expect($tx->id)->toBeInt();
});
