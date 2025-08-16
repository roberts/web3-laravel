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
	'default_rpc' => env('WEB3_DEFAULT_RPC', 'http://localhost:8545'),
	'default_chain_id' => env('WEB3_DEFAULT_CHAIN_ID', 8453),
	'request_timeout' => env('WEB3_REQUEST_TIMEOUT', 10),
	'networks' => [
		// 1 => 'https://mainnet.infura.io/v3/xxx',
		// 8453 => 'https://mainnet.base.org',
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

Asynchronous Operations: All state-changing transactions should be handled as queued jobs. The package should provide a base job class or a command to handle transaction submission, which prevents the web application from being blocked while waiting for a transaction to be mined.

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
