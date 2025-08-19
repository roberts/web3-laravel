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

    public function getCode(string $address, string $blockTag = 'latest'): string;

    /** @return array<string,mixed>|null */
    public function getTransactionByHash(string $txHash): ?array;

    /** @return array<string,mixed>|null */
    public function getBlockByNumber(string $blockTagOrHex, bool $fullTransactions = false): ?array;

    /** @return array<string,mixed>|null */
    public function getBlockByHash(string $blockHash, bool $fullTransactions = false): ?array;

    public function getStorageAt(string $address, string $position, string $blockTag = 'latest'): string;

    public function maxPriorityFeePerGas(): string;

    /**
     * eth_feeHistory: returns {oldestBlock, reward, baseFeePerGas, gasUsedRatio}
     * @return array<string,mixed>
     */
    public function feeHistory(int $blockCount, string $newestBlock = 'latest', array $rewardPercentiles = []): array;
}
