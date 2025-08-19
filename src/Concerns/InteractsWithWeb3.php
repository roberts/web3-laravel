<?php

namespace Roberts\Web3Laravel\Concerns;

use InvalidArgumentException;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;

trait InteractsWithWeb3
{
    // Removed web3.php helpers; native client only.

    // Public helpers
    public function getBalance(string $blockTag = 'latest'): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->getBalance($this->address, $blockTag);
        }
        throw new InvalidArgumentException('getBalance not supported for protocol');
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
        throw new InvalidArgumentException('getTransactionCount not supported for protocol');
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

    // Eloquent-style send using TransactionService
    public function send(array $tx): string
    {
        /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
        $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);

        return $svc->sendRaw($this, $tx);
    }
}
