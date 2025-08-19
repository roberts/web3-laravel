<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers sui protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::SUI);
    expect($adapter->protocol())->toBe(BlockchainProtocol::SUI);
});

it('creates a sui wallet (placeholder)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::SUI);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::SUI)->and($w->address)->not->toBe('');
});
