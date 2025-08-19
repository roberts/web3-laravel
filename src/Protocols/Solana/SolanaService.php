<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;

/**
 * Protocol-scoped Solana service that forwards to the SolanaProtocolAdapter.
 * Preferred over the legacy Services\SolanaService.
 */
class SolanaService
{
    public function __construct(private ProtocolRouter $router) {}

    private function adapter(): SolanaProtocolAdapter
    {
        /** @var SolanaProtocolAdapter $adapter */
        $adapter = $this->router->for(BlockchainProtocol::SOLANA);
        return $adapter;
    }

    /** Create a Solana wallet. */
    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        return $this->adapter()->createWallet($attributes, $owner, $blockchain);
    }

    /** Get native balance (lamports) as a decimal string. */
    public function getNativeBalance(Wallet $wallet): string
    {
        return $this->adapter()->getNativeBalance($wallet);
    }

    /** Transfer native SOL; amount is lamports as a decimal string. Returns signature. */
    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        return $this->adapter()->transferNative($from, $toAddress, $amount);
    }

    /** Validate a Solana address. */
    public function validateAddress(string $address): bool
    {
        return $this->adapter()->validateAddress($address);
    }
}
