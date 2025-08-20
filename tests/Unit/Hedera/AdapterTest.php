<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers hedera protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::HEDERA);
    expect($adapter->protocol())->toBe(BlockchainProtocol::HEDERA);
});

it('creates a hedera wallet (placeholder)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::HEDERA);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::HEDERA)->and($w->address)->not->toBe('');
});
