<?php

namespace Roberts\Web3Laravel\Core\Rpc;

interface ClientInterface
{
    /**
     * Perform a JSON-RPC call.
     * @param string $method
     * @param array<int,mixed> $params
     * @return mixed
     */
    public function call(string $method, array $params = []);
}
