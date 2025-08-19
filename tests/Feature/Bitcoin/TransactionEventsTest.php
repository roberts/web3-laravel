<?php

use Illuminate\Support\Facades\Event;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Events\TransactionFailed;
use Roberts\Web3Laravel\Events\TransactionPrepared;
use Roberts\Web3Laravel\Events\TransactionPreparing;
use Roberts\Web3Laravel\Events\TransactionSubmitted;
use Roberts\Web3Laravel\Jobs\PrepareTransaction;
use Roberts\Web3Laravel\Jobs\SubmitTransaction;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('fires preparing/prepared or failed events for bitcoin during staging', function () {
    Event::fake();

    $wallet = Wallet::createForProtocol(BlockchainProtocol::BITCOIN);

    $tx = Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => $wallet->address,
        'value' => '1',
    ]));

    PrepareTransaction::dispatchSync($tx->id);

    Event::assertDispatched(TransactionPreparing::class);
    expect(Event::dispatched(TransactionPrepared::class)->count() > 0
        || Event::dispatched(TransactionFailed::class)->count() > 0)->toBeTrue();
});

it('submits or fails bitcoin tx broadcast and emits an event', function () {
    Event::fake();

    $wallet = Wallet::createForProtocol(BlockchainProtocol::BITCOIN);
    $tx = Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => $wallet->address,
        'value' => '1',
    ]));

    SubmitTransaction::dispatchSync($tx->id);

    expect(Event::dispatched(TransactionSubmitted::class)->count() > 0
        || Event::dispatched(TransactionFailed::class)->count() > 0)->toBeTrue();
});
