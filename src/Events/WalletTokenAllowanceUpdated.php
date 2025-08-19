<?php

namespace Roberts\Web3Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;

class WalletTokenAllowanceUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Wallet $owner,
        public string $spender,
        public Token $token,
        public string $oldAllowance,
        public string $newAllowance,
    ) {}
}
