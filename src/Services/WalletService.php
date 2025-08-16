<?php

namespace Roberts\Web3Laravel\Services;

use Elliptic\EC;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Web3\Utils as Web3Utils;

class WalletService
{
    /**
     * Generate a new wallet (secp256k1) and persist it.
     * - Derives Ethereum address from the generated private key.
     * - Encrypts key via Wallet mutator.
     *
     * @param  Model|Authenticatable|null  $owner  Optional owner model; when null, owner fields are left null.
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
        $hash = Web3Utils::sha3('0x'.$pubNoPrefix); // 0x-prefixed keccak
        $address = '0x'.substr(Web3Utils::stripZero($hash), -40);
        $address = strtolower($address);

        $data = array_merge([
            'address' => $address,
            'key' => $privHex, // encrypted by mutator
            'blockchain_id' => $blockchain?->getKey(),
            'is_active' => true,
        ], $attributes);

        if ($owner instanceof Model) {
            $data['owner_type'] = $owner->getMorphClass();
            $data['owner_id'] = $owner->getKey();
        }

        return Wallet::create($data);
    }
}
