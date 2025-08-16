<?php

namespace Roberts\Web3Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Models\Transaction;

class TransactionRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(public Transaction $transaction)
    {
    }
}
