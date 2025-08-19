<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers cardano protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::CARDANO);
    expect($adapter->protocol())->toBe(BlockchainProtocol::CARDANO);
});

it('creates a cardano wallet (placeholder)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::CARDANO);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::CARDANO)->and($w->address)->not->toBe('');
});
