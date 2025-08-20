<?php

namespace Roberts\Web3Laravel\Protocols\Contracts;

use Roberts\Web3Laravel\Models\Wallet;

/** Optional adapter capability: provide account nonce/sequence. */
interface HasSequence
{
    /** Return the current account sequence/nonce (EVM: hex string; XRPL: int). */
    public function sequence(Wallet $wallet): int|string|null;
}
