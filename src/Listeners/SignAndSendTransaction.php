<?php

namespace Roberts\Web3Laravel\Listeners;

use Roberts\Web3Laravel\Events\TransactionRequested;
use Roberts\Web3Laravel\Jobs\SubmitTransaction;

class SignAndSendTransaction
{
    public function handle(TransactionRequested $event): void
    {
        SubmitTransaction::dispatch($event->transaction->id);
    }
}
