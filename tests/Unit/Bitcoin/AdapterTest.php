<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers bitcoin protocol adapter in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::BITCOIN);
    expect($adapter->protocol())->toBe(BlockchainProtocol::BITCOIN);
});

it('creates a bitcoin wallet (placeholder)', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for(BlockchainProtocol::BITCOIN);
    $w = $adapter->createWallet();
    expect($w->protocol)->toBe(BlockchainProtocol::BITCOIN)->and($w->address)->not->toBe('');
});
