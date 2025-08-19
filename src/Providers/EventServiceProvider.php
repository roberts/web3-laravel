<?php

namespace Roberts\Web3Laravel\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Roberts\Web3Laravel\Events\TransactionRequested;
use Roberts\Web3Laravel\Listeners\SignAndSendTransaction;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TransactionRequested::class => [
            SignAndSendTransaction::class,
        ],
    ];

    protected $subscribe = [
        \Roberts\Web3Laravel\Listeners\SendBalanceUpdateWebhook::class,
    ];
}
