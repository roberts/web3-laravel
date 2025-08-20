<?php

namespace Roberts\Web3Laravel\Protocols\Bitcoin;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface;

class BitcoinJsonRpcClient
{
    public function __construct(private ClientInterface $rpc) {}

    public function getRawTransaction(string $txid, bool $verbose = true): ?array
    {
        $res = $this->rpc->call('getrawtransaction', [$txid, $verbose]);

        return is_array($res) ? $res : null;
    }

    public function sendRawTransaction(string $hex): string
    {
        $res = $this->rpc->call('sendrawtransaction', [$hex]);

        return (string) $res;
    }

    public function getBlockCount(): int
    {
        $res = $this->rpc->call('getblockcount', []);

        return (int) ($res ?? 0);
    }

    public function getBlock(string $hash): ?array
    {
        $res = $this->rpc->call('getblock', [$hash]);

        return is_array($res) ? $res : null;
    }
}
