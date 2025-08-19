<?php

namespace Roberts\Web3Laravel\Concerns;

use InvalidArgumentException;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Web3Laravel as Web3Manager;
use Web3\Web3;

trait InteractsWithWeb3
{
    /** Get a Web3 client for this wallet's blockchain (EVM only). */
    public function web3(): Web3
    {
        /** @var Web3Manager $manager */
        $manager = app(Web3Manager::class);

        // Without a bound blockchain, fall back to configured defaults.
        return $manager->web3(null, null);
    }

    /** Low-level ETH proxy. */
    protected function eth(): \Web3\Eth
    {
        /** @var \Roberts\Web3Laravel\Web3Laravel $manager */
        $manager = app(\Roberts\Web3Laravel\Web3Laravel::class);

        return $manager->ethFrom($this->web3());
    }

    /** Helper to call an eth method and return its result synchronously. */
    protected function ethCall(string $method, array $args = [])
    {
        $result = null;
        $error = null;
        $cb = function ($err, $res) use (&$error, &$result) {
            $error = $err;
            $result = $res;
        };
        $args[] = $cb;
        $this->eth()->{$method}(...$args);
        if ($error) {
            if ($error instanceof \Throwable) {
                throw $error;
            }
            throw new InvalidArgumentException('eth call error');
        }

        return $result;
    }

    // Public helpers
    public function getBalance(string $blockTag = 'latest'): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm() && config('web3-laravel.driver') === 'native') {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->getBalance($this->address, $blockTag);
        }

        $balance = $this->ethCall('getBalance', [strtolower($this->address), $blockTag]);
        if (is_object($balance) && method_exists($balance, 'toString')) {
            return (string) $balance->toString();
        }

        return (string) $balance;
    }

    // Eloquent-style alias
    public function balance(string $blockTag = 'latest'): string
    {
        return $this->getBalance($blockTag);
    }

    public function getTransactionCount(string $blockTag = 'latest'): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm() && config('web3-laravel.driver') === 'native') {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->getTransactionCount($this->address, $blockTag);
        }

        $nonce = $this->ethCall('getTransactionCount', [strtolower($this->address), $blockTag]);
        if (is_object($nonce) && method_exists($nonce, 'toString')) {
            return (string) $nonce->toString();
        }

        return (string) $nonce;
    }

    // Eloquent-style alias
    public function nonce(string $blockTag = 'latest'): string
    {
        return $this->getTransactionCount($blockTag);
    }

    public function getGasPrice(): string
    {
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm() && config('web3-laravel.driver') === 'native') {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);

            return $evm->gasPrice();
        }

        $price = $this->ethCall('gasPrice');
        if (is_object($price) && method_exists($price, 'toString')) {
            return (string) $price->toString();
        }

        return (string) $price;
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
        if (method_exists($this, 'protocol') && $this->protocol instanceof BlockchainProtocol && $this->protocol->isEvm() && config('web3-laravel.driver') === 'native') {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            $payload = array_merge(['from' => strtolower($this->address)], $tx);

            return $evm->estimateGas($payload, $blockTag);
        }

        $params = array_merge([
            'from' => strtolower($this->address),
        ], $tx);

        $gas = $this->ethCall('estimateGas', [$params, $blockTag]);
        if (is_object($gas) && method_exists($gas, 'toString')) {
            return (string) $gas->toString();
        }

        return (string) $gas;
    }

    // Eloquent-style send using TransactionService
    public function send(array $tx): string
    {
        /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
        $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);

        return $svc->sendRaw($this, $tx);
    }
}
