<?php

namespace Roberts\Web3Laravel\Protocols\Evm;

interface EvmClientInterface
{
    public function chainId(): int;

    public function getBalance(string $address, string $blockTag = 'latest'): string;

    public function gasPrice(): string;

    public function estimateGas(array $tx, string $blockTag = 'latest'): string;

    public function getTransactionCount(string $address, string $blockTag = 'latest'): string;

    public function sendRawTransaction(string $rawTx): string;

    public function call(array $tx, string $blockTag = 'latest'): string;

    public function blockNumber(): string;

    /** @return array<string,mixed>|null */
    public function getTransactionReceipt(string $txHash): ?array;

    /** @return array<int,mixed> */
    public function getLogs(array $filter): array;
}
