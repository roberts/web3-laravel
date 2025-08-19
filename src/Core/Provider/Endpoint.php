<?php

namespace Roberts\Web3Laravel\Core\Provider;

class Endpoint
{
    public function __construct(
        public string $rpc,
        public int $weight = 1,
        public array $headers = [],
    ) {}
}
