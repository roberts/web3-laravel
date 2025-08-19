<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers ton protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::TON);
    expect($adapter->protocol())->toBe(BlockchainProtocol::TON);
});

it('creates a ton wallet (placeholder)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::TON);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::TON)->and($w->address)->not->toBe('');
});
