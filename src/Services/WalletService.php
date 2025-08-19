<?php

namespace Roberts\Web3Laravel\Services;

use Elliptic\EC;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Support\Hex;
use Roberts\Web3Laravel\Support\Keccak;

class WalletService
{
    /**
     * Generate a new EVM wallet (secp256k1) and persist it.
     * - Derives Ethereum-compatible address from the generated private key.
     * - Encrypts key via Wallet mutator.
     */
    public function create(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();

        // private key: 32 bytes hex, 0x-prefixed
        $privHex = '0x'.str_pad($keyPair->getPrivate('hex'), 64, '0', STR_PAD_LEFT);

        // public key (uncompressed, hex, without 0x04 prefix -> use x+y)
        $pub = $keyPair->getPublic(false, 'hex'); // 04 + x(64) + y(64)
        $pubNoPrefix = substr($pub, 2);

        // address = last 20 bytes of keccak256(public_key)
        $hash = Keccak::hash($pubNoPrefix, true); // 0x-prefixed keccak of public key (no 0x)
        $address = '0x'.substr(Hex::stripZero($hash), -40);
        $address = strtolower($address);

        $data = array_merge([
            'address' => $address,
            'key' => $privHex, // encrypted by mutator
            'protocol' => $blockchain ? $blockchain->protocol : BlockchainProtocol::EVM,
            'is_active' => true,
        ], $attributes);

        if ($owner instanceof Model) {
            $data['owner_id'] = $owner->getKey();
        }

        return Wallet::create($data);
    }

    /**
     * Create a wallet for a specific blockchain, routing to the correct protocol implementation.
     * - EVM chains use secp256k1 generation (this service).
     * - Solana uses ed25519 via SolanaService.
     */
    public function createForBlockchain(Blockchain $blockchain, array $attributes = [], ?Model $owner = null): Wallet
    {
        if ($blockchain->protocol->isSolana()) {
            // Pass owner via attributes for Solana service
            if ($owner instanceof Model) {
                $attributes['owner_id'] = $owner->getKey();
            }

            /** @var \Roberts\Web3Laravel\Services\SolanaService $solana */
            $solana = app(\Roberts\Web3Laravel\Services\SolanaService::class);

            return $solana->createWallet($blockchain->getKey(), $attributes);
        }

        // Default to EVM
        return $this->create($attributes, $owner, $blockchain);
    }

    /**
     * Convenience: resolve by blockchain id and create accordingly.
     */
    public function createForBlockchainId(int $blockchainId, array $attributes = [], ?Model $owner = null): Wallet
    {
        $blockchain = Blockchain::query()->findOrFail($blockchainId);

        return $this->createForBlockchain($blockchain, $attributes, $owner);
    }

    /** Create a wallet by protocol, choosing defaults for each chain family. */
    public function createForProtocol(BlockchainProtocol $protocol, array $attributes = [], ?Model $owner = null): Wallet
    {
        if ($protocol->isSolana()) {
            if ($owner instanceof Model) {
                $attributes['owner_id'] = $owner->getKey();
            }
            /** @var \Roberts\Web3Laravel\Services\SolanaService $solana */
            $solana = app(\Roberts\Web3Laravel\Services\SolanaService::class);

            return $solana->createWallet(null, $attributes);
        }

        return $this->create($attributes, $owner, null);
    }
}
