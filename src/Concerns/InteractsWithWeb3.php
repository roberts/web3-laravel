<?php

namespace Roberts\Web3Laravel\Concerns;

use InvalidArgumentException;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Protocols\CostEstimatorRouter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Services\BalanceService;

trait InteractsWithWeb3
{
    // Public helpers
    public function getBalance(string $blockTag = 'latest'): string
    {
    // Route via BalanceService for all protocols
    /** @var BalanceService $svc */
    $svc = app(BalanceService::class);

    return $svc->native($this);
    }

    // Eloquent-style alias
    public function balance(string $blockTag = 'latest'): string
    {
        return $this->getBalance($blockTag);
    }

    public function getTransactionCount(string $blockTag = 'latest'): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->getTransactionCount($this->address, $blockTag);
        }
    throw new InvalidArgumentException('getTransactionCount/nonce not available for this protocol');
    }

    // Eloquent-style alias
    public function nonce(string $blockTag = 'latest'): string
    {
        return $this->getTransactionCount($blockTag);
    }

    public function getGasPrice(): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->gasPrice();
        }
        throw new InvalidArgumentException('gasPrice not supported for protocol');
    }

    // Eloquent-style alias
    public function gasPrice(): string
    {
        return $this->getGasPrice();
    }

    /**
     * Estimate gas for a transaction from this address.
     *
     * @param  array  $tx  Example: ['to' => '0x..', 'value' => '0x..', 'data' => '0x..']
     * @return string Hex quantity (0x...)
     */
    public function estimateGas(array $tx, string $blockTag = 'latest'): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            $payload = array_merge(['from' => strtolower($this->address)], $tx);

            return $evm->estimateGas($payload, $blockTag);
        }
        throw new InvalidArgumentException('estimateGas not supported for protocol');
    }

    /**
     * Chain-agnostic cost estimation helper using per-protocol estimators.
     * Returns an array like ['total_required' => string, 'unit' => string, 'details' => array].
     */
    public function estimateCost(array $tx = []): array
    {
        /** @var CostEstimatorRouter $router */
        $router = app(CostEstimatorRouter::class);
        $estimator = $router->for($this->protocol);

        // Build an in-memory Transaction for estimation
        $t = new Transaction([
            'to' => $tx['to'] ?? null,
            'value' => $tx['value'] ?? null,
            'data' => $tx['data'] ?? null,
            'gas_limit' => $tx['gas'] ?? $tx['gasLimit'] ?? null,
            'gwei' => $tx['gasPrice'] ?? null,
            'fee_max' => $tx['maxFeePerGas'] ?? null,
            'priority_max' => $tx['maxPriorityFeePerGas'] ?? null,
            'is_1559' => $tx['type'] === 2 || isset($tx['maxFeePerGas']) || isset($tx['maxPriorityFeePerGas']) ? true : null,
            'nonce' => $tx['nonce'] ?? null,
            'chain_id' => $tx['chainId'] ?? null,
            'meta' => $tx['meta'] ?? [],
        ]);

        return $estimator->estimateAndPopulate($t, $this);
    }

    // Eloquent-style send using TransactionService
    public function send(array $tx): string
    {
        // Prefer protocol adapters for non-EVM; retain direct EVM path via TransactionService
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm()) {
            /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
            $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);

            return $svc->sendRaw($this, $tx);
        }

        // Build a transient Transaction and submit via adapter
        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        $adapter = $router->for($this->protocol);

        if (! $adapter instanceof \Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter) {
            throw new InvalidArgumentException('Sending not supported for protocol');
        }

        $t = new Transaction([
            'to' => $tx['to'] ?? null,
            'value' => $tx['value'] ?? null,
            'data' => $tx['data'] ?? null,
            'gas_limit' => $tx['gas'] ?? $tx['gasLimit'] ?? null,
            'gwei' => $tx['gasPrice'] ?? null,
            'fee_max' => $tx['maxFeePerGas'] ?? null,
            'priority_max' => $tx['maxPriorityFeePerGas'] ?? null,
            'is_1559' => $tx['type'] === 2 || isset($tx['maxFeePerGas']) || isset($tx['maxPriorityFeePerGas']) ? true : null,
            'nonce' => $tx['nonce'] ?? null,
            'chain_id' => $tx['chainId'] ?? null,
            'meta' => $tx['meta'] ?? [],
        ]);

        // Best-effort preparation and cost estimation
        try {
            $adapter->prepareTransaction($t, $this);
            /** @var CostEstimatorRouter $costRouter */
            $costRouter = app(CostEstimatorRouter::class);
            $est = $costRouter->for($this->protocol);
            $est->estimateAndPopulate($t, $this);
        } catch (\Throwable) {
        }

        return $adapter->submitTransaction($t, $this);
    }
}
