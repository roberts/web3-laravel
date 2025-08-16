<?php

namespace Roberts\Web3Laravel\Listeners;

use Roberts\Web3Laravel\Events\TransactionRequested;
use Roberts\Web3Laravel\Jobs\PrepareTransaction;

class SignAndSendTransaction
{
    public function handle(TransactionRequested $event): void
    {
    PrepareTransaction::dispatch($event->transaction->id);
    }
}
