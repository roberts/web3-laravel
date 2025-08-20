<?php

namespace Roberts\Web3Laravel\Protocols\Hedera;

use Roberts\Web3Laravel\Models\Wallet;

interface HederaSdkInterface
{
    /**
     * Create a fungible token via a Hedera SDK using the signer wallet stored in the host app.
     * Should perform server-side signing using the wallet's key.
     *
     * Expected params include: name, symbol, decimals, initial_supply, recipient (optional address).
     * Must return an array with keys: tokenId (string), txId (string).
     */
    public function createFungibleToken(Wallet $signer, array $params): array;
}
