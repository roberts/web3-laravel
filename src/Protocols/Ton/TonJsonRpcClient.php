<?php

namespace Roberts\Web3Laravel\Protocols\Ton;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface;

class TonJsonRpcClient
{
    public function __construct(private ClientInterface $rpc) {}

    public function sendBoc(string $bocBase64): array
    {
        // Placeholder; integrate Toncenter or compatible API
        return $this->rpc->call('sendBoc', [['boc' => $bocBase64]]);
    }

    public function estimateFees(array $msg): array
    {
        return $this->rpc->call('estimateFees', [$msg]);
    }
}
