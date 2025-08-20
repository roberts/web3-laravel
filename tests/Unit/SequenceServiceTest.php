<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Protocols\Xrpl\XrplJsonRpcClient;
use Roberts\Web3Laravel\Services\SequenceService;

it('returns EVM nonce (hex) via SequenceService', function () {
    // Bind a fake EVM client
    app()->bind(EvmClientInterface::class, function () {
        return new class implements EvmClientInterface
        {
            public function chainId(): int
            {
                return 1;
            }

            public function getBalance(string $address, string $blockTag = 'latest'): string
            {
                return '0x0';
            }

            public function gasPrice(): string
            {
                return '0x1';
            }

            public function maxPriorityFeePerGas(): string
            {
                return '0x1';
            }

            public function estimateGas(array $tx, string $blockTag = 'latest'): string
            {
                return '0x5208';
            }

            public function getTransactionCount(string $address, string $blockTag = 'latest'): string
            {
                return '0x5';
            }

            public function sendRawTransaction(string $raw): string
            {
                return '0xhash';
            }

            public function call(array $tx, string $blockTag = 'latest'): string
            {
                return '0x';
            }

            public function blockNumber(): string
            {
                return '0x1';
            }

            public function getTransactionReceipt(string $hash): ?array
            {
                return null;
            }

            public function getLogs(array $filter): array
            {
                return [];
            }

            public function getCode(string $address, string $blockTag = 'latest'): string
            {
                return '0x';
            }

            public function getTransactionByHash(string $txHash): ?array
            {
                return null;
            }

            public function getBlockByNumber(string $blockTagOrHex, bool $fullTransactions = false): ?array
            {
                return null;
            }

            public function getBlockByHash(string $blockHash, bool $fullTransactions = false): ?array
            {
                return null;
            }

            public function getStorageAt(string $address, string $position, string $blockTag = 'latest'): string
            {
                return '0x0';
            }

            public function feeHistory(int $blockCount, string $newestBlock = 'latest', array $rewardPercentiles = []): array
            {
                return [
                    'oldestBlock' => '0x0',
                    'reward' => [],
                    'baseFeePerGas' => [],
                    'gasUsedRatio' => [],
                ];
            }
        };
    });

    $wallet = Wallet::factory()->create(['protocol' => BlockchainProtocol::EVM]);

    $svc = app(SequenceService::class);
    expect($svc->current($wallet))->toBe('0x5');
});

it('returns XRPL Sequence (int) via SequenceService', function () {
    // Bind a fake XRPL client
    app()->bind(XrplJsonRpcClient::class, function () {
        return new class extends XrplJsonRpcClient
        {
            public function __construct() {}

            public function accountInfo(string $address): array
            {
                return ['account_data' => ['Sequence' => 10]];
            }
        };
    });

    $wallet = Wallet::factory()->create([
        'protocol' => BlockchainProtocol::XRPL,
        'address' => 'rTESTADDRESS',
    ]);

    $svc = app(SequenceService::class);
    expect($svc->current($wallet))->toBe(10);
});
