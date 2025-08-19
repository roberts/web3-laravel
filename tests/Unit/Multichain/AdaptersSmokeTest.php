<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('registers new protocol adapters in router', function () {
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    foreach ([BlockchainProtocol::BITCOIN, BlockchainProtocol::SUI, BlockchainProtocol::XRPL, BlockchainProtocol::CARDANO, BlockchainProtocol::HEDERA] as $proto) {
        $adapter = $router->for($proto);
        expect($adapter->protocol())->toBe($proto);
    }
});

it('creates wallets for sui, xrpl, cardano, hedera (placeholders)', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    foreach ([BlockchainProtocol::SUI, BlockchainProtocol::XRPL, BlockchainProtocol::CARDANO, BlockchainProtocol::HEDERA] as $proto) {
        $adapter = $router->for($proto);
        $w = $adapter->createWallet();
        expect($w->protocol)->toBe($proto)->and($w->address)->not->toBe('');
    }
});
