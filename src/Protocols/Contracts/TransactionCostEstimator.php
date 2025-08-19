<?php

namespace Roberts\Web3Laravel\Protocols\Contracts;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * Per-protocol cost estimator. Responsible for populating any chain-specific fee fields on Transaction
 * and returning a total required native amount (smallest unit) to cover value + fees.
 */
interface TransactionCostEstimator
{
    /**
     * Estimate and populate chain-specific cost fields on $tx. Return an array like:
     * [
     *   'total_required' => string, // decimal string in smallest unit
     *   'unit' => string,           // e.g., 'wei','lamports','drops','mist'
     *   'details' => array          // optional, chain-specific
     * ]
     */
    public function estimateAndPopulate(Transaction $tx, Wallet $wallet): array;
}
