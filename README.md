# Web3 Laravel — protocol‑first, multi‑chain adapters with native EVM JSON‑RPC

[![Latest Version on Packagist](https://img.shields.io/packagist/v/roberts/web3-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/web3-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/roberts/web3-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/roberts/web3-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/roberts/web3-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/roberts/web3-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/roberts/web3-laravel.svg?style=flat-square)](https://packagist.org/packages/roberts/web3-laravel)

This Laravel package provides a protocol‑first, chain‑agnostic toolkit to create wallets and interact with multiple chains via per‑protocol adapters. It includes a native EVM JSON‑RPC client and built‑in transaction signer (legacy + EIP‑1559). No web3.php dependency.

## Supported blockchains

Web3 Laravel is chain‑agnostic with per‑protocol adapters and a router that picks the right one based on the wallet. High‑level support:

- EVM (Ethereum‑compatible): wallets, native transfers, ERC‑20 approve/transfer
- Solana: wallets, native SOL, SPL approve/transfer, SPL token deploy helper
- XRPL: wallets, server‑side IOU issuance flow (with optional auto‑trustline)
- Sui: wallets, native SUI transfers, Coin Factory token creation
- Bitcoin: wallets, transaction flow stubs
- Cardano: wallets; SDK‑first token mint flow with proxy/stub fallbacks
- Hedera: wallets; SDK‑first token create flow with proxy/stub fallbacks
- TON: wallets; Jetton deploy via SDK or sendBoc, with stub fallback

See the docs below for per‑chain details and configuration.

## Token deployment (multi‑chain)

You can launch fungible tokens via a single, chain-agnostic API. See the full guide with per-chain details, configuration, and examples:

- docs/deploytokens.md — Deploying Fungible Tokens (Solana SPL, Sui Coin Factory, Hedera HTS, Cardano Native Assets, XRPL IOU, TON Jetton)

## Docs

- docs/deploytokens.md — Multi‑chain token deployment guide
- docs/transactions.md — Transaction pipeline: prepare → submit → confirm
- docs/sdk-integrations.md — SDK‑first integrations for Hedera, Cardano, and TON

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
    // Optional client tuning
    'rpc' => [
        'retries' => 2,
        'backoff_ms' => 200,
        'headers' => [
            // 'Authorization' => 'Bearer ...',
        ],
    ],
    'confirmations_required' => env('WEB3_CONFIRMATIONS_REQUIRED', 6),
    'confirmations_poll_interval' => env('WEB3_CONFIRMATIONS_POLL_INTERVAL', 10),
];
```

## Usage (EVM example)

```php
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;

// Resolve the native EVM JSON-RPC client
/** @var EvmClientInterface $evm */
$evm = app(EvmClientInterface::class);

$blockHex = $evm->blockNumber();
$gasPriceHex = $evm->gasPrice();
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

### Multi‑chain token creation (async)

Create a token via a custodial/shared wallet and let the pipeline handle the rest (see docs for per‑chain options):

```php
use Roberts\Web3Laravel\Models\Wallet;

$signer = Wallet::find($signerWalletId);
$tx = $signer->createFungibleToken([
    'protocol' => 'solana', // or use 'blockchain_id' to target a specific chain
    'name' => 'Example',
    'symbol' => 'EXM',
    'decimals' => 9,
    'initial_supply' => '1000000000',
]);
// Track $tx->status and $tx->tx_hash; see docs/deploytokens.md
```
```

### Wallet ownership (User model)

Wallets are owned by your application’s User model via a nullable `owner_id` foreign key.

```php
use App\Models\User;
use Roberts\Web3Laravel\Models\Wallet;

$user = User::first();

// Create a wallet and associate to a user
$wallet = Wallet::create([
	'address' => '0x...',
	'key' => '0x...', // will be encrypted by the model mutator
	'owner_id' => $user->id,
]);

// Or via the service (recommended): generates keys and sets owner automatically
$wallet = app(Roberts\Web3Laravel\Services\WalletService::class)
	->create([], $user);

// Access the owner and query by owner
$owner = $wallet->user; // belongsTo the configured auth user model
$wallets = Wallet::forUser($user)->get();
```

You can also use the built-in ping command to verify connectivity:

```bash
php artisan web3:ping
```

## Transactions & events

We provide a chain‑agnostic Transaction model with a fully async prepare/submit/confirm pipeline and lifecycle events. See the full guide for details, examples, and per‑protocol behavior:

- docs/transactions.md — Transactions: model, async pipeline, events, confirmations

## Additional docs and examples

- Explore tests/ for end‑to‑end examples (wallets, adapters, transactions, events).
- See docs/deploytokens.md for token creation across chains and CLI usage.

## Features - Core Functionality

RPC & Provider Management: The package should allow for easy configuration of the blockchain's RPC endpoint (like Base). It should handle the instantiation of the Web3 class and its provider, making it available throughout the Laravel application via a Facade or service container.

Wallet Management: A key feature is the ability to securely handle Ethereum wallets. The package should provide a method to:

Generate new wallets with the package's native key engines (secp256k1 and ed25519), without any web3.php dependency.

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
