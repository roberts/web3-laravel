<?php

// PHPStan stub for dynamic callback-based methods provided by web3p/web3.php
// This file only provides signatures for analysis and is not loaded at runtime.

namespace Web3 {
    class Web3
    {
        /** @param callable(mixed|null, mixed):void $callback */
        public function clientVersion(callable $callback): void {}
    }
}

namespace Web3 {
    class Eth
    {
        /** @param callable(mixed|null, mixed):void $callback */
        public function blockNumber(callable $callback): void {}

        /** @param string $txHash @param callable(mixed|null, mixed):void $callback */
        public function getTransactionReceipt(string $txHash, callable $callback): void {}

        /** @param array<string,mixed> $tx @param string $blockTag @param callable(mixed|null, mixed):void $callback */
        public function call(array $tx, string $blockTag, callable $callback): void {}

        /** @param string $rawTx @param callable(mixed|null, mixed):void $callback */
        public function sendRawTransaction(string $rawTx, callable $callback): void {}

        /** @param callable(mixed|null, mixed):void $callback */
        public function gasPrice(callable $callback): void {}

        /** @param string $address @param string $blockTag @param callable(mixed|null, mixed):void $callback */
        public function getBalance(string $address, string $blockTag, callable $callback): void {}

        /** @param string $address @param string $blockTag @param callable(mixed|null, mixed):void $callback */
        public function getTransactionCount(string $address, string $blockTag, callable $callback): void {}

        /** @param callable(mixed|null, mixed):void $callback */
        public function maxPriorityFeePerGas(callable $callback): void {}

        /** @param array<string,mixed> $tx @param string $blockTag @param callable(mixed|null, mixed):void $callback */
        public function estimateGas(array $tx, string $blockTag, callable $callback): void {}
    }
}
