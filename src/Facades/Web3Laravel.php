<?php

namespace Roberts\Web3Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Roberts\Web3Laravel\Web3Laravel
 */
class Web3Laravel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Roberts\Web3Laravel\Web3Laravel::class;
    }
}
