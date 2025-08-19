<?php

namespace Roberts\Web3Laravel\Core\Provider;

class Pool
{
    /** @var list<Endpoint> */
    protected array $endpoints;
    protected int $index = 0;

    /** @param list<Endpoint> $endpoints */
    public function __construct(array $endpoints)
    {
        $this->endpoints = $endpoints;
    }

    public function pick(): Endpoint
    {
        if (count($this->endpoints) === 0) {
            throw new \RuntimeException('No RPC endpoints configured');
        }
        // Simple round-robin for now
        $ep = $this->endpoints[$this->index % count($this->endpoints)];
        $this->index++;

        return $ep;
    }
}
