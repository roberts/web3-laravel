<?php

namespace Roberts\Web3Laravel\Protocols\Evm\Drivers;

use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Web3Laravel;

class Web3PhpAdapter implements EvmClientInterface
{
    public function __construct(
        protected Web3Laravel $manager
    ) {}

    public function chainId(): int
    {
        $eth = $this->manager->eth();
        $result = $this->manager->ethCall($eth, 'chainId');
        return (int) hexdec(substr((string) $result, 2));
    }

    public function getBalance(string $address, string $blockTag = 'latest'): string
    {
    $eth = $this->manager->eth();
    $result = $this->manager->ethCall($eth, 'getBalance', [strtolower($address), $blockTag]);
        return is_object($result) && method_exists($result, 'toString') ? (string) $result->toString() : (string) $result;
    }

    public function gasPrice(): string
    {
    $eth = $this->manager->eth();
    $result = $this->manager->ethCall($eth, 'gasPrice');
        return is_object($result) && method_exists($result, 'toString') ? (string) $result->toString() : (string) $result;
    }

    public function estimateGas(array $tx, string $blockTag = 'latest'): string
    {
    $eth = $this->manager->eth();
    $payload = $tx;
    $result = $this->manager->ethCall($eth, 'estimateGas', [$payload, $blockTag]);
        return is_object($result) && method_exists($result, 'toString') ? (string) $result->toString() : (string) $result;
    }

    public function getTransactionCount(string $address, string $blockTag = 'latest'): string
    {
    $eth = $this->manager->eth();
    $result = $this->manager->ethCall($eth, 'getTransactionCount', [strtolower($address), $blockTag]);
        return is_object($result) && method_exists($result, 'toString') ? (string) $result->toString() : (string) $result;
    }

    public function sendRawTransaction(string $rawTx): string
    {
    $eth = $this->manager->eth();
    $result = $this->manager->ethCall($eth, 'sendRawTransaction', [$rawTx]);
    return (string) $result;
    }

    public function call(array $tx, string $blockTag = 'latest'): string
    {
        $eth = $this->manager->eth();
        $result = $this->manager->ethCall($eth, 'call', [$tx, $blockTag]);
        return (string) $result;
    }

    public function blockNumber(): string
    {
        $eth = $this->manager->eth();
        $result = $this->manager->ethCall($eth, 'blockNumber');
        return (string) $result;
    }

    public function getTransactionReceipt(string $txHash): ?array
    {
        $eth = $this->manager->eth();
        $result = $this->manager->ethCall($eth, 'getTransactionReceipt', [$txHash]);
        return is_array($result) ? $result : null;
    }

    public function getLogs(array $filter): array
    {
        $eth = $this->manager->eth();
        $result = $this->manager->ethCall($eth, 'getLogs', [$filter]);
        return is_array($result) ? $result : [];
    }
}
