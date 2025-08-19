<?php

namespace Roberts\Web3Laravel\Support;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

final class Protocol
{
    public static function adapter(BlockchainProtocol $protocol): ProtocolAdapter
    {
        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);

        return $router->for($protocol);
    }
}
