<?php

namespace Roberts\Web3Laravel\Protocols\Cardano;

use Roberts\Web3Laravel\Models\Wallet;

interface CardanoSdkInterface
{
    /**
     * Mint a native asset using a Cardano SDK with server-side signing.
     *
     * Expected params: name, symbol, decimals, initial_supply, recipient (optional address).
     * Must return an array with keys: assetId (string), txHash (string).
     */
    public function mintNativeAsset(Wallet $signer, array $params): array;
}
