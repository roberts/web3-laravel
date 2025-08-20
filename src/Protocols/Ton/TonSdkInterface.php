<?php

namespace Roberts\Web3Laravel\Protocols\Ton;

use Roberts\Web3Laravel\Models\Wallet;

interface TonSdkInterface
{
    /**
     * Deploy a Jetton master contract and optionally mint initial supply to a recipient.
     *
     * Expected params: name, symbol, decimals, initial_supply, recipient (optional address).
     * Must return an array with keys: master (string), txHash (string).
     */
    public function deployJetton(Wallet $signer, array $params): array;
}
