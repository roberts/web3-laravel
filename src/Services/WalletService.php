<?php

namespace Roberts\Web3Laravel\Services;

use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

class WalletService
{
    /** Create and persist a wallet using the appropriate protocol adapter. */
    public function create(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);

    $protocol = $blockchain->protocol ?? BlockchainProtocol::EVM;
        $adapter = $router->for($protocol);

        return $adapter->createWallet($attributes, $owner, $blockchain);
    }

    /**
     * Create a wallet for a specific blockchain, routing to the correct protocol implementation.
     * Uses the ProtocolRouter; Solana/EVM specifics are handled in their adapters.
     */
    public function createForBlockchain(Blockchain $blockchain, array $attributes = [], ?Model $owner = null): Wallet
    {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for($blockchain->protocol);

    return $adapter->createWallet($attributes, $owner, $blockchain);
    }

    /**
     * Convenience: resolve by blockchain id and create accordingly.
     */
    public function createForBlockchainId(int $blockchainId, array $attributes = [], ?Model $owner = null): Wallet
    {
        $blockchain = Blockchain::query()->findOrFail($blockchainId);

        return $this->createForBlockchain($blockchain, $attributes, $owner);
    }

    /** Create a wallet by protocol, choosing defaults for each chain family. */
    public function createForProtocol(BlockchainProtocol $protocol, array $attributes = [], ?Model $owner = null): Wallet
    {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for($protocol);

    return $adapter->createWallet($attributes, $owner, null);
    }
}
