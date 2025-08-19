<?php

namespace Roberts\Web3Laravel\Protocols\Contracts;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * Per-protocol transaction pipeline hooks used by the orchestration jobs.
 * Implement minimally per chain; jobs remain protocol-agnostic.
 */
interface ProtocolTransactionAdapter
{
    /** Prepare/stage fields on the Transaction prior to submission (do not save). */
    public function prepareTransaction(Transaction $tx, Wallet $wallet): void;

    /** Build, sign, and broadcast the transaction; return signature/hash. */
    public function submitTransaction(Transaction $tx, Wallet $wallet): string;

    /**
     * Determine confirmation status and context.
     * Returns: [
     *   'confirmed' => bool,
     *   'confirmations' => int,
     *   'receipt' => mixed|null,
     *   'blockNumber' => int|null,
     * ]
     */
    public function checkConfirmations(Transaction $tx, Wallet $wallet): array;
}
