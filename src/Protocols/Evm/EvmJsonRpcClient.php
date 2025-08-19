<?php

namespace Roberts\Web3Laravel\Protocols\Evm;

use Roberts\Web3Laravel\Core\Rpc\ClientInterface as RpcClient;

class EvmJsonRpcClient implements EvmClientInterface
{
    public function __construct(
        protected RpcClient $rpc
    ) {}

    public function chainId(): int
    {
        $hex = (string) $this->rpc->call('eth_chainId');
        return (int) hexdec(substr($hex, 2));
    }

    public function getBalance(string $address, string $blockTag = 'latest'): string
    {
        return (string) $this->rpc->call('eth_getBalance', [strtolower($address), $blockTag]);
    }

    public function gasPrice(): string
    {
        return (string) $this->rpc->call('eth_gasPrice');
    }

    public function estimateGas(array $tx, string $blockTag = 'latest'): string
    {
        $payload = array_merge(['from' => strtolower($tx['from'] ?? '')], $tx);
        return (string) $this->rpc->call('eth_estimateGas', [$payload, $blockTag]);
    }

    public function getTransactionCount(string $address, string $blockTag = 'latest'): string
    {
        return (string) $this->rpc->call('eth_getTransactionCount', [strtolower($address), $blockTag]);
    }

    public function sendRawTransaction(string $rawTx): string
    {
        return (string) $this->rpc->call('eth_sendRawTransaction', [$rawTx]);
    }

    public function call(array $tx, string $blockTag = 'latest'): string
    {
        return (string) $this->rpc->call('eth_call', [$tx, $blockTag]);
    }

    public function blockNumber(): string
    {
        return (string) $this->rpc->call('eth_blockNumber');
    }

    public function getTransactionReceipt(string $txHash): ?array
    {
        $res = $this->rpc->call('eth_getTransactionReceipt', [$txHash]);
        return is_array($res) ? $res : null;
    }

    public function getLogs(array $filter): array
    {
        $res = $this->rpc->call('eth_getLogs', [$filter]);
        return is_array($res) ? $res : [];
    }
}
