<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('creates and updates a transaction record', function () {
    $wallet = Wallet::createForProtocol(BlockchainProtocol::SOLANA);

    $tx = Transaction::factory()->create([
        'wallet_id' => $wallet->id,
    ]);

    $tx->update(['status' => $tx->status]); // no-op change

    expect($tx->id)->toBeInt();
});
