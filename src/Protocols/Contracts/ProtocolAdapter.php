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

    /** Get fungible token balance for an address (chain-agnostic). */
    public function getTokenBalance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress): string;

    /** Get allowance for owner->spender on a fungible token (chain-agnostic). */
    public function allowance(\Roberts\Web3Laravel\Models\Token $token, string $ownerAddress, string $spenderAddress): string;

    /**
     * Transfer fungible tokens for a given token. Amount is a decimal string in base units.
     * Returns a transaction signature/hash.
     */
    public function transferToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $from, string $toAddress, string $amount): string;

    /** Approve a spender for a fungible token. Amount is a decimal string in base units. */
    public function approveToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress, string $amount): string;

    /** Revoke a spender approval for a fungible token (set to zero). */
    public function revokeToken(\Roberts\Web3Laravel\Models\Token $token, \Roberts\Web3Laravel\Models\Wallet $owner, string $spenderAddress): string;
}
