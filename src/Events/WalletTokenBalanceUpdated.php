<?php

namespace Roberts\Web3Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletToken;

class WalletTokenBalanceUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Wallet $wallet,
        public Token $token,
        public string $oldBalance,
        public string $newBalance,
        public ?WalletToken $record = null,
    ) {}
}
