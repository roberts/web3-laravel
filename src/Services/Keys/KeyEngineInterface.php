<?php

namespace Roberts\Web3Laravel\Services\Keys;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;

interface KeyEngineInterface
{
    /** Generate a BIP39 mnemonic phrase. */
    public function generateMnemonic(int $words = 12): string;

    /** Derive a seed (hex) from mnemonic+optional passphrase. */
    public function seedFromMnemonic(string $mnemonic, string $passphrase = ''): string;

    /** Derive a keypair for given protocol/coin/path from seed. Returns [privHex, pubBytes]. */
    public function deriveKeypair(BlockchainProtocol $protocol, int $coinType, string $path, string $seedHex): array;

    /** Generate a random keypair for protocol/scheme without HD. Returns [privHex, pubBytes]. */
    public function randomKeypair(BlockchainProtocol $protocol, string $scheme = 'default'): array;

    /** Convert public key bytes to canonical address for protocol/network. */
    public function publicKeyToAddress(BlockchainProtocol $protocol, string $network, string $scheme, string $publicKeyBytes): string;
}
