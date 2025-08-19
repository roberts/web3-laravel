<?php

namespace Roberts\Web3Laravel\Services;

use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

class BalanceService
{
    public function __construct(private ProtocolRouter $router) {}

    /** Get native currency balance in base units (wei/lamports) as decimal string. */
    public function native(Wallet $wallet): string
    {
        $adapter = $this->router->for($wallet->protocol);
        return $adapter->getNativeBalance($wallet);
    }

    /** Get fungible token balance for a wallet, routed by protocol. */
    public function token(Token $token, Wallet $wallet): string
    {
        $adapter = $this->router->for($wallet->protocol);
        return $adapter->getTokenBalance($token, $wallet->address);
    }

    /** Get allowance owner->spender for a token, routed by protocol. */
    public function allowance(Token $token, string $ownerAddress, string $spenderAddress, Wallet $contextWallet): string
    {
        $adapter = $this->router->for($contextWallet->protocol);
        return $adapter->allowance($token, $ownerAddress, $spenderAddress);
    }
}
