<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers xrpl protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::XRPL);
    expect($adapter->protocol())->toBe(BlockchainProtocol::XRPL);
});

it('creates an xrpl wallet (placeholder)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::XRPL);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::XRPL)->and($w->address)->not->toBe('');
});
