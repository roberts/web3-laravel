<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Tuupola\Base58;

class SolanaProtocolAdapter implements ProtocolAdapter
{
    public function __construct(private SolanaJsonRpcClient $rpc)
    {
    }

    public function protocol(): BlockchainProtocol
    {
        return BlockchainProtocol::SOLANA;
    }

    public function createWallet(array $attributes = [], ?Model $owner = null, ?Blockchain $blockchain = null): Wallet
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('ext-sodium is required for Solana key generation');
        }

        $kp = \sodium_crypto_sign_keypair();
        $secret = \sodium_crypto_sign_secretkey($kp);
        $public = \sodium_crypto_sign_publickey($kp);
        $address = (new Base58(['characters' => Base58::BITCOIN]))->encode($public);
        $encryptedKey = Crypt::encryptString(bin2hex($secret));

        $data = array_merge([
            'address' => $address,
            'key' => $encryptedKey,
            'protocol' => BlockchainProtocol::SOLANA,
            'is_active' => true,
        ], $attributes);

        if ($owner instanceof Model) {
            $data['owner_id'] = $owner->getKey();
        }

        if ($blockchain) {
            $data['blockchain_id'] = $blockchain->getKey();
        }

        return Wallet::create($data);
    }

    public function getNativeBalance(Wallet $wallet): string
    {
        return (string) $this->rpc->getBalance($wallet->address);
    }

    public function transferNative(Wallet $from, string $toAddress, string $amount): string
    {
        // Placeholder for later milestone: build v0 message, sign, and send.
        throw new \RuntimeException('transferNative not yet implemented for Solana');
    }

    public function normalizeAddress(string $address): string
    {
        // Base58 addresses are used as-is for Solana.
        return $address;
    }

    public function validateAddress(string $address): bool
    {
        // Simple base58 length range check; proper ed25519 pubkey validation can be added later.
        return $address !== '' && strlen($address) >= 32 && strlen($address) <= 44;
    }
}
