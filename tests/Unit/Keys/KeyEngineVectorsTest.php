<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;

/** Golden vectors for address codecs and derivation. */
it('derives Sui address from SLIP-0010 ed25519 seed and path', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var KeyEngineInterface $keys */
    $keys = app(KeyEngineInterface::class);
    // mnemonic: abandon x11 + about (deterministic seed)
    $seedHex = bin2hex(hash('sha512', 'test-seed-sui', true));
    [$sk, $pk] = $keys->deriveKeypair(BlockchainProtocol::SUI, 784, "m/44'/784'/0'/0'/0'", $seedHex);
    $addr = $keys->publicKeyToAddress(BlockchainProtocol::SUI, 'mainnet', 'ed25519', $pk);
    expect($addr)->toMatch('/^0x[0-9a-f]{64}$/');
});

it('derives XRPL classic address from SLIP-0010 ed25519 seed and path', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }
    /** @var KeyEngineInterface $keys */
    $keys = app(KeyEngineInterface::class);
    $seedHex = bin2hex(hash('sha512', 'test-seed-xrpl', true));
    [$sk, $pk] = $keys->deriveKeypair(BlockchainProtocol::XRPL, 144, "m/44'/144'/0'/0'/0'", $seedHex);
    $addr = $keys->publicKeyToAddress(BlockchainProtocol::XRPL, 'mainnet', 'ed25519', $pk);
    expect($addr)->toMatch('/^[r][1-9A-HJ-NP-Za-km-z]{25,34}$/');
});

it('derives Bitcoin P2WPKH testnet address from BIP32 secp256k1 seed and path', function () {
    /** @var KeyEngineInterface $keys */
    $keys = app(KeyEngineInterface::class);
    $seedHex = bin2hex(hash('sha512', 'test-seed-btc', true));
    [$sk, $pk] = $keys->deriveKeypair(BlockchainProtocol::BITCOIN, 0, "m/84'/1'/0'/0/0", $seedHex);
    $addr = $keys->publicKeyToAddress(BlockchainProtocol::BITCOIN, 'testnet', 'secp256k1', $pk);
    expect($addr)->toStartWith('tb1');
});
