<?php

namespace Roberts\Web3Laravel\Services;

use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Tuupola\Base58;

class SolanaService
{
    /** Create and persist a Solana wallet (ed25519), returning the Wallet model. */
    public function createWallet(?int $blockchainId = null, array $attributes = []): Wallet
    {
        if (! extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana key generation');
        }

        // Select blockchain row for Solana
        $blockchain = $this->resolveBlockchain($blockchainId);

        // Generate a random Ed25519 keypair
        $kp = \sodium_crypto_sign_keypair();
        $secret = \sodium_crypto_sign_secretkey($kp); // 64 bytes: seed(32) + public(32)
        $public = \sodium_crypto_sign_publickey($kp); // 32 bytes

        // Address is base58 of the public key
        $address = (new Base58(['characters' => Base58::BITCOIN]))->encode($public);

        // Encrypt the secret for storage (hex-encode to be safe in text storage)
        $encryptedKey = Crypt::encryptString(bin2hex($secret));

        $data = array_merge([
            'address' => $address,
            'key' => $encryptedKey,
            'protocol' => \Roberts\Web3Laravel\Enums\BlockchainProtocol::SOLANA,
            'is_active' => true,
        ], $attributes);

        return Wallet::create($data);
    }

    protected function resolveBlockchain(?int $blockchainId = null): ?Blockchain
    {
        if ($blockchainId) {
            return Blockchain::query()->where('id', $blockchainId)->first();
        }

        return Blockchain::query()
            ->where('protocol', BlockchainProtocol::SOLANA)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
    }
}
