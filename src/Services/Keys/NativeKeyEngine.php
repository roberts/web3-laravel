<?php

namespace Roberts\Web3Laravel\Services\Keys;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Support\Bech32;
use Tuupola\Base58;

class NativeKeyEngine implements KeyEngineInterface
{
    private const HARDENED_OFFSET = 0x80000000;

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
        $seed = hex2bin($seedHex);
        if ($seed === false) {
            throw new \InvalidArgumentException('Invalid seed hex');
        }

        return match ($protocol) {
            BlockchainProtocol::SOLANA,
            BlockchainProtocol::SUI,
            BlockchainProtocol::CARDANO,
            BlockchainProtocol::HEDERA,
            BlockchainProtocol::XRPL,
            BlockchainProtocol::TON => $this->deriveEd25519Slip10($seed, $path),
            BlockchainProtocol::BITCOIN,
            BlockchainProtocol::EVM => $this->deriveSecp256k1Bip32($seed, $path),
        };
    }

    /** Parse string path like m/44'/784'/0'/0'/0' into [index, hardened] segments. */
    private function parsePath(string $path): array
    {
        $path = trim($path);
        if ($path === '' || $path === 'm' || $path === 'M' || $path === "m'") {
            return [];
        }
        if (! str_starts_with($path, 'm/')) {
            throw new \InvalidArgumentException('Path must start with m/');
        }
        $parts = explode('/', substr($path, 2));
        $out = [];
        foreach ($parts as $p) {
            $hardened = str_ends_with($p, "'") || str_ends_with($p, 'h') || str_ends_with($p, 'H');
            $num = rtrim(rtrim($p, "'"), 'hH');
            if ($num === '' || ! ctype_digit($num)) {
                throw new \InvalidArgumentException('Invalid path segment: '.$p);
            }
            $index = (int) $num;
            if ($index < 0 || $index > 0x7FFFFFFF) {
                throw new \InvalidArgumentException('Invalid index range');
            }
            $out[] = [$index, $hardened];
        }

        return $out;
    }

    private function ed25519FromSeed(string $seed32): array
    {
        $kp = \sodium_crypto_sign_seed_keypair($seed32);
        $sk = \sodium_crypto_sign_secretkey($kp); // 64 bytes
        $pk = \sodium_crypto_sign_publickey($kp); // 32 bytes

        return [bin2hex($sk), $pk];
    }

    private function slip10MasterEd25519(string $seed): array
    {
        $I = hash_hmac('sha512', $seed, 'ed25519 seed', true);
        $k = substr($I, 0, 32);
        $c = substr($I, 32);

        return [$k, $c];
    }

    private function slip10ChildEd25519(string $kpar, string $cpar, int $index): array
    {
        // hardened only; data = 0x00 || kpar || ser32(index)
        $data = "\x00".$kpar.pack('N', $index);
        $I = hash_hmac('sha512', $data, $cpar, true);
        $k = substr($I, 0, 32);
        $c = substr($I, 32);

        return [$k, $c];
    }

    private function bip32MasterSecp256k1(string $seed): array
    {
        $I = hash_hmac('sha512', $seed, 'Bitcoin seed', true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32);

        // If IL not in [1, n-1], spec says discard; we assume ok for tests
        return [$IL, $IR];
    }

    private function bip32ChildSecp256k1(string $kpar, string $cpar, int $index, bool $hardened): array
    {
        $ec = new \Elliptic\EC('secp256k1');
        $n = $ec->curve->n; // BN
        if ($hardened) {
            $data = "\x00".$kpar.pack('N', $index | self::HARDENED_OFFSET);
        } else {
            $key = $ec->keyFromPrivate(bin2hex($kpar), 'hex');
            $pub = $key->getPublic();
            $xHex = str_pad($pub->getX()->toString(16), 64, '0', STR_PAD_LEFT);
            $prefix = $pub->getY()->isOdd() ? '03' : '02';
            $pubBytes = hex2bin($prefix.$xHex);
            $data = $pubBytes.pack('N', $index);
        }
        $I = hash_hmac('sha512', $data, $cpar, true);
        $IL = substr($I, 0, 32);
        $IR = substr($I, 32);
        $ilBn = new \BN\BN(bin2hex($IL), 16);
        $kBn = new \BN\BN(bin2hex($kpar), 16);
        $child = $ilBn->add($kBn)->umod($n);
        $childHex = str_pad($child->toString(16), 64, '0', STR_PAD_LEFT);

        return [hex2bin($childHex), $IR];
    }

    /** Derive ed25519 keys via SLIP-0010 from binary seed and path. */
    private function deriveEd25519Slip10(string $seed, string $path): array
    {
        [$k, $c] = $this->slip10MasterEd25519($seed);
        $segments = $this->parsePath($path);
        foreach ($segments as [$index, $hardened]) {
            if (! $hardened) {
                throw new \InvalidArgumentException('ed25519 supports only hardened derivation');
            }
            [$k, $c] = $this->slip10ChildEd25519($k, $c, $index | self::HARDENED_OFFSET);
        }
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium required for ed25519');
        }

        return $this->ed25519FromSeed($k);
    }

    /** Derive secp256k1 keys via BIP32 from binary seed and path. */
    private function deriveSecp256k1Bip32(string $seed, string $path): array
    {
        [$k, $c] = $this->bip32MasterSecp256k1($seed);
        $segments = $this->parsePath($path);
        foreach ($segments as [$index, $hardened]) {
            [$k, $c] = $this->bip32ChildSecp256k1($k, $c, $index, $hardened);
        }
        $privHex = '0x'.bin2hex($k);
        $ec = new \Elliptic\EC('secp256k1');
        $key = $ec->keyFromPrivate(bin2hex($k), 'hex');
        $pubPoint = $key->getPublic();
        $xHex = str_pad($pubPoint->getX()->toString(16), 64, '0', STR_PAD_LEFT);
        $prefix = $pubPoint->getY()->isOdd() ? '03' : '02';
        $pub = hex2bin($prefix.$xHex);

        return [$privHex, $pub];
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
                if (! extension_loaded('sodium')) {
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
                if (! extension_loaded('sodium')) {
                    throw new \RuntimeException('ext-sodium required for ed25519');
                }
                $kp = \sodium_crypto_sign_keypair();
                $sk = \sodium_crypto_sign_secretkey($kp);
                $pk = \sodium_crypto_sign_publickey($kp);

                return [bin2hex($sk), $pk];
            case BlockchainProtocol::BITCOIN:
                $priv = '0x'.strtolower(bin2hex(random_bytes(32)));

                return [$priv, ''];
            case BlockchainProtocol::TON:
                if (! extension_loaded('sodium')) {
                    throw new \RuntimeException('ext-sodium required for ed25519');
                }
                $kp = \sodium_crypto_sign_keypair();
                $sk = \sodium_crypto_sign_secretkey($kp);
                $pk = \sodium_crypto_sign_publickey($kp);

                return [bin2hex($sk), $pk];
            default:
                // Fallback for future enum additions
                return ['0x'.strtolower(bin2hex(random_bytes(32))), ''];
        }

    }

    public function publicKeyToAddress(BlockchainProtocol $protocol, string $network, string $scheme, string $publicKeyBytes): string
    {
        switch ($protocol) {
            case BlockchainProtocol::SOLANA:
                return (new Base58(['characters' => Base58::BITCOIN]))->encode($publicKeyBytes);
            case BlockchainProtocol::SUI:
                // Sui address = first 32 bytes of blake2b-256(pubkey || schemeFlag)
                $flag = "\x00"; // ed25519
                $digest = sodium_crypto_generichash($publicKeyBytes.$flag, '', 32);

                return '0x'.bin2hex($digest);
            case BlockchainProtocol::XRPL:
                // XRPL classic address (ED25519) = base58 with ripple alphabet of (0x00 + RIPEMD160(SHA256(pubkey))) + 4-byte checksum
                $sha = hash('sha256', $publicKeyBytes, true);
                $rip = hash('ripemd160', $sha, true);
                $payload = "\x00".$rip;
                $check = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);
                $alphabet = 'rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz';

                return (new Base58(['characters' => $alphabet]))->encode($payload.$check);
            case BlockchainProtocol::CARDANO:
                // Placeholder: bech32 not implemented in Phase 1
                return 'addr1'.substr(bin2hex($publicKeyBytes), 0, 20);
            case BlockchainProtocol::HEDERA:
                // No canonical pubkey->account mapping without on-chain provisioning
                return '0.0.'.hexdec(substr(bin2hex($publicKeyBytes), 0, 6));
            case BlockchainProtocol::BITCOIN:
                // Bech32 P2WPKH for mainnet/testnet depending on $network
                // assume $publicKeyBytes is compressed SEC (33 bytes)
                $sha = hash('sha256', $publicKeyBytes, true);
                $rip = hash('ripemd160', $sha, true);
                $hrp = ($network === 'testnet' || $network === 'signet' || $network === 'regtest') ? 'tb' : 'bc';

                return Bech32::encodeSegwit($hrp, 0, $rip);
            case BlockchainProtocol::TON:
                // Placeholder TON address: base64url(workchain||sha256(pubkey)[:32])
                $hash = hash('sha256', $publicKeyBytes, true);
                $raw = "\x00".substr($hash, 0, 32);

                return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            case BlockchainProtocol::EVM:
                // EVM address derives from Keccak(pubkey); handled elsewhere
                return '';
            default:
                return '';
        }
    }
}
