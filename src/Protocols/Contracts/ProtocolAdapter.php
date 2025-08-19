<?php

namespace Roberts\Web3Laravel\Protocols\Contracts;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * Minimal cross-protocol adapter focused on native balance/transfer and address handling.
 * Expand incrementally (tokens, allowances, NFTs) per milestones.
 */
interface ProtocolAdapter
{
    public function protocol(): BlockchainProtocol;

    /** Create and persist a wallet on this protocol. */
    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet;

    /** Return native balance as a decimal string in base units (wei/lamports). */
    public function getNativeBalance(Wallet $wallet): string;

    /** Transfer native currency; amount is decimal string in base units. Returns tx signature/hash. */
    public function transferNative(Wallet $from, string $toAddress, string $amount): string;

    /** Normalize address for storage/lookup. */
    public function normalizeAddress(string $address): string;

    /** Validate address format for this protocol. */
    public function validateAddress(string $address): bool;
}
