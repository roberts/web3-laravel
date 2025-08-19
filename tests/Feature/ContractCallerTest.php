<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

// Chain-agnostic root: verify wiring and leave protocol specifics to protocol suites.
it('resolves protocol adapters via the router for both protocols', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);

    $evm = $router->for(BlockchainProtocol::EVM);
    $sol = $router->for(BlockchainProtocol::SOLANA);

    expect($evm)->toBeInstanceOf(ProtocolAdapter::class)
        ->and($sol)->toBeInstanceOf(ProtocolAdapter::class);
});
