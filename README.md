# Laravel package wrapper for web3.php

[![Latest Version on Packagist](https://img.shields.io/packagist/v/roberts/web3-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/web3-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/roberts/web3-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/roberts/web3-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/roberts/web3-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/roberts/web3-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/roberts/web3-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/web3-laravel)

This Laravel package creates a wrapper for the functionality I want out of the web3.php package. Please review the featurees below:

## Installation

You can install the package via composer:

```bash
composer require roberts/web3-laravel
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="web3-laravel-migrations"
php artisan migrate

# Optional: seed common chains
php artisan db:seed --class="Roberts\\Web3Laravel\\Database\\Seeders\\BlockchainSeeder"
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="web3-laravel-config"
```

This is the contents of the published config file (defaults shown):

```php
return [
	'use_database' => env('WEB3_USE_DATABASE', true),
	'default_rpc' => env('WEB3_DEFAULT_RPC', 'https://mainnet.base.org'),
	'default_chain_id' => env('WEB3_DEFAULT_CHAIN_ID', 8453),
	'request_timeout' => env('WEB3_REQUEST_TIMEOUT', 10),
	'networks' => [
		// 1 => 'https://mainnet.infura.io/v3/xxx',
		8453 => 'https://mainnet.base.org',
		// 84532 => 'https://sepolia.base.org',
	],
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="web3-laravel-views"
```

## Usage

```php
use Roberts\Web3Laravel\Facades\Web3Laravel as Web3M;

// Resolve a Web3 client for default chain (config or DB):
$web3 = Web3M::web3();
$web3->clientVersion(function ($err, $version) {
	if ($err) { throw $err; }
	dump($version);
});

// Resolve for a specific chain id:
$web3 = Web3M::web3(8453); // Base mainnet

// Or force a specific RPC URL:
$web3 = Web3M::web3(null, 'https://mainnet.base.org');

// Get the resolved RPC URL without instantiating:
$rpc = Web3M::resolveRpcUrl(8453);
```

Eloquent-style helpers:

```php
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\Contract;

$wallet = Wallet::first();
$balance = $wallet->balance(); // getBalance()
$nonce = $wallet->nonce();     // getTransactionCount()
$gas = $wallet->gasPrice();    // getGasPrice()

// Estimate gas for a potential transaction from this wallet
$estimatedGasHex = $wallet->estimateGas([
	'to' => '0x0000000000000000000000000000000000000000',
	'value' => 1000,
	// 'data' => '0x...', // optional
]);

// Send a transaction (legacy fields; signing library required)
$txHash = $wallet->send([
	'to' => '0x0000000000000000000000000000000000000000',
	'value' => 1000,
]);

// Contract read-only call using stored ABI on the model
$contract = Contract::first();
$result = $contract->call('balanceOf', [$wallet->address]);
```

You can also use the built-in ping command to verify connectivity:

```bash
php artisan web3:ping --chainId=8453
```

## Transactions: Eloquent model & async pipeline

This package provides a `Transaction` Eloquent model and a fully asynchronous submission flow so your app doesn’t block while sending transactions.

- Create a transaction record; it auto-estimates gas and suggests EIP-1559 fees if you don’t provide them.
- On `created`, an event fires and a queued job handles signing and broadcasting.
- The model is updated with the resulting `tx_hash` and `status`.

Example: create and queue a transaction

```php
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

$wallet = Wallet::first();

$tx = Transaction::create([
	'wallet_id' => $wallet->id,
	'to' => '0x0000000000000000000000000000000000000000',
	'value' => '0x3e8', // 1000 wei (supports hex or decimal string)
	// Omit gas_limit/fees to let the package estimate & suggest 1559 fees automatically
	// 'data' => '0x...', // for contract method calls
]);

// Shortly after, the queued job will set:
// $tx->status  => 'submitted' (or 'failed')
// $tx->tx_hash => '0x...'
```

What gets filled automatically when omitted:

- gas_limit: estimated via `eth_estimateGas` with a small 12% safety buffer.
- is_1559: defaults to true.
- priority_max: suggested from `eth_maxPriorityFeePerGas` (fallback 1 gwei).
- fee_max: suggested from `eth_gasPrice` when needed.

Immediate send vs tracked send:

- If you want a quick fire-and-forget send, call `$wallet->send([...])` to sign and broadcast immediately (no DB record).
- If you need tracking, create a `Transaction` model as shown above; the package handles queuing and status updates for you.

Statuses and fields:

- `status`: `pending` on create, `submitted` after broadcast, `confirmed` when enough confirmations are observed, `failed` on error (with `error` message).
- `tx_hash`: set on success.
- Other fields include `nonce`, `chain_id`, `data`, `access_list`, and optional contract function metadata if you store it.

Confirmations tracking

- After a transaction is submitted, the package dispatches a confirmation polling job that:
	- fetches the transaction receipt periodically,
	- compares the current block to the receipt’s blockNumber,
	- marks the record `confirmed` once confirmations >= `web3-laravel.confirmations_required` (default 6).
- You can tune the threshold in `config/web3-laravel.php`:

```php
// config/web3-laravel.php
'confirmations_required' => 6,
```

WebSocket/event-driven mode

- Set the mode and (optionally) a WS endpoint in `config/web3-laravel.php`:

```php
'confirmations_mode' => 'websocket',
'default_ws' => 'wss://your-node.example/ws',
// or per-chain mapping:
'ws_networks' => [
	8453 => 'wss://mainnet.base.org',
],
```

- Run the watcher (long-running):

```bash
php artisan web3:watch-confirmations --chainId=8453 --interval=5
```

- The watcher subscribes to new blocks (when supported) and dispatches confirmation checks for submitted transactions immediately.

Advanced: manual estimation & fees

```php
// Service-level helpers are available if you need them
$svc = app(Roberts\Web3Laravel\Services\TransactionService::class);
$gasHex = $svc->estimateGas($wallet, ['to' => $to, 'value' => $value, 'data' => $data]);
$fees = $svc->suggestFees($wallet); // ['priority' => '0x..', 'max' => '0x..']
```

## Features - Core Functionality

RPC & Provider Management: The package should allow for easy configuration of the blockchain's RPC endpoint (like Base). It should handle the instantiation of the Web3 class and its provider, making it available throughout the Laravel application via a Facade or service container.

Wallet Management: A key feature is the ability to securely handle Ethereum wallets. The package should provide a method to:

Generate new wallets using web3.php's offline key generation.

Encrypt and decrypt private keys using Laravel's built-in Crypt facade, ensuring private keys are never stored in plain text.

Manage multiple wallets, allowing the application to select a specific wallet for a transaction.

Transaction Execution: The package should provide methods for executing both state-changing and read-only functions. This includes:

Sending ETH: A simple method to send Ether from a Laravel-managed wallet to any address on the Base chain.

Calling Contract Functions: A fluent API for calling "view" or "pure" functions that don't modify the blockchain state.

Executing Contract Functions: A secure way to execute state-changing functions that require a signed transaction and gas. This method should handle the signing, nonce management, and broadcasting of the transaction.

Asynchronous Operations: All state-changing transactions are handled as queued jobs. Creating a `Transaction` model dispatches an event that enqueues a job (`SubmitTransaction`) to sign and broadcast, preventing the web application from blocking. The model is updated with `status` and `tx_hash` automatically.

Additional Features
ABI Management: The package could include functionality to retrieve and cache ABIs from the database, block explorers, or local files, making it easy to interact with a contract without manually loading the ABI each time.

Event Listening: It could provide an event listener or a scheduled command to monitor for specific events emitted by a smart contract on the blockchain.

Unit Conversion: Helper functions for converting between different units of currency (like Wei, Gwei, and Ether) would be very useful.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Drew Roberts](https://github.com/drewroberts)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
