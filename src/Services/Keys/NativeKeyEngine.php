<?php

namespace Roberts\Web3Laravel\Services\Keys;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Tuupola\Base58;

class NativeKeyEngine implements KeyEngineInterface
{
    public function generateMnemonic(int $words = 12): string
    {
        // Simple placeholder: random hex words. Replace with proper BIP39 library in Phase 2.
        $count = max(12, min(24, $words));
        $parts = [];
        for ($i = 0; $i < $count; $i++) {
            $parts[] = bin2hex(random_bytes(2));
        }

        return implode(' ', $parts);
    }

    public function seedFromMnemonic(string $mnemonic, string $passphrase = ''): string
    {
        // Placeholder PBKDF2 derivation; replace with proper BIP39 seed
        $salt = 'mnemonic'.$passphrase;
        return bin2hex(hash_pbkdf2('sha512', $mnemonic, $salt, 2048, 64, true));
    }

    public function deriveKeypair(BlockchainProtocol $protocol, int $coinType, string $path, string $seedHex): array
    {
        // Phase 1: not implementing full HD; fallback to randomKeypair
        return $this->randomKeypair($protocol);
    }

    public function randomKeypair(BlockchainProtocol $protocol, string $scheme = 'default'): array
    {
        switch ($protocol) {
            case BlockchainProtocol::EVM:
                // secp256k1
                $priv = '0x'.strtolower(bin2hex(random_bytes(32)));
                // Public key derivation deferred to adapter/services when needed
                return [$priv, ''];
            case BlockchainProtocol::SOLANA:
            case BlockchainProtocol::SUI:
            case BlockchainProtocol::CARDANO:
            case BlockchainProtocol::HEDERA:
                if (!extension_loaded('sodium')) {
                    throw new \RuntimeException('ext-sodium required for ed25519');
                }
                $kp = \sodium_crypto_sign_keypair();
                $sk = \sodium_crypto_sign_secretkey($kp);
                $pk = \sodium_crypto_sign_publickey($kp);
                return [bin2hex($sk), $pk];
            case BlockchainProtocol::XRPL:
                if ($scheme === 'secp256k1') {
                    $priv = '0x'.strtolower(bin2hex(random_bytes(32)));
                    return [$priv, ''];
                }
                if (!extension_loaded('sodium')) {
                    throw new \RuntimeException('ext-sodium required for ed25519');
                }
                $kp = \sodium_crypto_sign_keypair();
                $sk = \sodium_crypto_sign_secretkey($kp);
                $pk = \sodium_crypto_sign_publickey($kp);
                return [bin2hex($sk), $pk];
            case BlockchainProtocol::BITCOIN:
                $priv = '0x'.strtolower(bin2hex(random_bytes(32)));
                return [$priv, ''];
        }

    // Fallback
    return ['0x'.strtolower(bin2hex(random_bytes(32))), ''];
    }

    public function publicKeyToAddress(BlockchainProtocol $protocol, string $network, string $scheme, string $publicKeyBytes): string
    {
        switch ($protocol) {
            case BlockchainProtocol::SOLANA:
                return (new Base58(['characters' => Base58::BITCOIN]))->encode($publicKeyBytes);
            case BlockchainProtocol::SUI:
                // Simplified: hex with 0x prefix
                return '0x'.bin2hex($publicKeyBytes);
            case BlockchainProtocol::XRPL:
                // Placeholder ripple base58 via standard alphabet (not exact). Replace with ripple alphabet and checksum.
                return (new Base58(['characters' => Base58::BITCOIN]))->encode($publicKeyBytes);
            case BlockchainProtocol::CARDANO:
                // Placeholder: bech32 not implemented in Phase 1
                return 'addr1'.substr(bin2hex($publicKeyBytes), 0, 20);
            case BlockchainProtocol::HEDERA:
                // No canonical pubkey->account mapping without on-chain provisioning
                return '0.0.'.hexdec(substr(bin2hex($publicKeyBytes), 0, 6));
            case BlockchainProtocol::BITCOIN:
                // Placeholder: return hex pubkey; actual P2PKH/P2WPKH address requires hashing & base58/bech32
                return '0x'.bin2hex($publicKeyBytes);
            case BlockchainProtocol::EVM:
                // EVM address derives from Keccak(pubkey); handled elsewhere
                return '';
        }

    return '';
    }
}
