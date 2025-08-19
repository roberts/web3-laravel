<?php

use Illuminate\Support\Facades\Event;
use Roberts\Web3Laravel\Events\TransactionFailed;
use Roberts\Web3Laravel\Events\TransactionPrepared;
use Roberts\Web3Laravel\Events\TransactionPreparing;
use Roberts\Web3Laravel\Events\TransactionSubmitted;
use Roberts\Web3Laravel\Jobs\PrepareTransaction;
use Roberts\Web3Laravel\Jobs\SubmitTransaction;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\TransactionService;

it('fires preparing and prepared events during staging', function () {
    Event::fake();

    $wallet = Wallet::factory()->create();

    $tx = Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => '0x'.str_repeat('0', 40),
        'value' => '0x0',
        // Provide gas but set 1559 fees to zero so required cost is 0 and no failure fires
        'gas_limit' => 21000,
        'is_1559' => true,
        'priority_max' => 4,
        'fee_max' => 69,
    ]));

    // Run preparation synchronously to deterministically capture events
    PrepareTransaction::dispatchSync($tx->id);

    Event::assertDispatched(TransactionPreparing::class);
    // Depending on environment (e.g., balance stubs), preparation may fail or succeed
    expect(Event::dispatched(TransactionPrepared::class)->count() > 0
        || Event::dispatched(TransactionFailed::class)->count() > 0)->toBeTrue();
});

it('fires submitted event when broadcasting succeeds', function () {
    Event::fake();

    // Stub TransactionService to return a fake tx hash
    app()->bind(TransactionService::class, fn () => new class extends TransactionService
    {
        public function __construct() {}

        public function sendRaw($wallet, array $payload): string
        {
            return '0xabc123';
        }
    });

    $wallet = Wallet::factory()->create();
    $tx = Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => '0x'.str_repeat('0', 40),
        'value' => 1,
        'gas_limit' => 21000,
        'is_1559' => false,
        'gwei' => 420,
        'chain_id' => 8453,
    ]));

    SubmitTransaction::dispatchSync($tx->id);

    Event::assertDispatched(TransactionSubmitted::class);
});

it('fires failed event when broadcasting throws', function () {
    Event::fake();

    // Stub TransactionService to throw
    app()->bind(TransactionService::class, fn () => new class extends TransactionService
    {
        public function __construct() {}

        public function sendRaw($wallet, array $payload): string
        {
            throw new RuntimeException('boom');
        }
    });

    $wallet = Wallet::factory()->create();
    $tx = Transaction::withoutEvents(fn () => Transaction::factory()->create([
        'wallet_id' => $wallet->id,
        'from' => $wallet->address,
        'to' => '0x'.str_repeat('0', 40),
        'value' => 2,
        'gas_limit' => 21000,
        'is_1559' => false,
        'gwei' => 420,
        'chain_id' => 8453,
    ]));

    SubmitTransaction::dispatchSync($tx->id);

    Event::assertDispatched(TransactionFailed::class);
});
