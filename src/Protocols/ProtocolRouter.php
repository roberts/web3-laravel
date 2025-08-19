<?php

namespace Roberts\Web3Laravel\Protocols;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolAdapter;

class ProtocolRouter
{
    /** @var array<string,ProtocolAdapter> */
    private array $adapters = [];

    public function register(ProtocolAdapter $adapter): void
    {
        $this->adapters[$adapter->protocol()->value] = $adapter;
    }

    public function for(BlockchainProtocol $protocol): ProtocolAdapter
    {
        $key = $protocol->value;
        if (! isset($this->adapters[$key])) {
            throw new \InvalidArgumentException("No adapter registered for protocol {$key}");
        }

        return $this->adapters[$key];
    }
}
