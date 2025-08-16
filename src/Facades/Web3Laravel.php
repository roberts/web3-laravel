<?php

namespace Roberts\Web3Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Web3\Web3 web3(?int $chainId = null, ?string $rpc = null)
 * @method static string resolveRpcUrl(?int $chainId = null)
 *
 * @see \Roberts\Web3Laravel\Web3Laravel
 */
class Web3Laravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Roberts\Web3Laravel\Web3Laravel::class;
    }
}
