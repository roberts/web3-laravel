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

// Synchronous client version helper (wraps callback-style API):
$version = Web3M::clientVersionString();

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

## Lifecycle events

Hook into transaction progress throughout the pipeline by listening to these events:

- `Roberts\\Web3Laravel\\Events\\TransactionPreparing`
- `Roberts\\Web3Laravel\\Events\\TransactionPrepared`
- `Roberts\\Web3Laravel\\Events\\TransactionSubmitted`
- `Roberts\\Web3Laravel\\Events\\TransactionConfirmed`
- `Roberts\\Web3Laravel\\Events\\TransactionFailed` (reason available via `$event->reason`)

Example listener registration (EventServiceProvider):

```php
protected $listen = [
	Roberts\\Web3Laravel\\Events\\TransactionSubmitted::class => [
		App\\Listeners\\NotifyOnSubmission::class,
	],
	Roberts\\Web3Laravel\\Events\\TransactionFailed::class => [
		App\\Listeners\\AlertOnFailure::class,
	],
];
```

Example minimal listener:

```php
namespace App\Listeners;

use Roberts\Web3Laravel\Events\TransactionSubmitted;

class NotifyOnSubmission
{
	public function handle(TransactionSubmitted $event): void
	{
		logger()->info('TX submitted', [
			'id' => $event->transaction->id,
			'hash' => $event->transaction->tx_hash,
		]);
	}
}
```

## Token & NFT Management: Clean Architecture with Separated Concerns

This package provides a comprehensive token and NFT management system with clean separation between fungible tokens (ERC-20) and non-fungible tokens (ERC-721/ERC-1155). The architecture is designed for scalability, analytics, and deployment platform integration.

### Core Architecture

- **Tokens**: Dedicated to ERC-20 fungible tokens with metadata support for deployed tokens
- **NFT Collections**: Manages NFT collection metadata and analytics
- **Wallet NFTs**: Direct ownership tracking with comprehensive metadata and rarity systems

```php
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\WalletNft;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Enums\TokenType;
```

### Fungible Tokens (ERC-20)

#### Token Creation & Management

```php
// Create a fungible token
$token = Token::create([
    'contract_id' => $contract->id,
    'symbol' => 'USDC',
    'name' => 'USD Coin',
    'decimals' => 6,
    'total_supply' => '1000000000000', // 1M USDC with 6 decimals
    'metadata' => [
        'icon_url' => 'https://example.com/usdc-icon.png',
        'description' => 'A stablecoin pegged to USD',
        'website' => 'https://centre.io/usdc',
        'social' => [
            'twitter' => '@centre_io',
            'telegram' => 't.me/centre_io',
        ],
        'deployer_metadata' => [
            'launch_date' => now(),
            'initial_liquidity' => '50 ETH',
            'platform' => 'Web3Laravel Deployer',
        ],
    ],
]);

// Token information and helpers
echo $token->getDisplayName();     // "USD Coin (USDC)"
echo $token->getFormattedSupply(); // "1,000,000 USDC"
echo $token->getIconUrl();         // From metadata
echo $token->getDescription();     // From metadata
echo $token->getWebsite();         // From metadata

// Check if token was deployed through your platform
if ($token->isDeployedToken()) {
    $deployerInfo = $token->getDeployerMetadata();
    echo $deployerInfo['platform']; // "Web3Laravel Deployer"
}
```

#### Token Operations

```php
// Balance operations with decimal handling
$balance = $token->getBalance('0x742d35Cc...'); // Raw balance string
$walletBalance = $token->getWalletBalance($wallet);

// Format amounts with proper decimals
$rawAmount = '1500000'; // 1.5 USDC with 6 decimals
$formatted = $token->formatAmount($rawAmount); // "1.5"
$parsed = $token->parseAmount('1.5'); // "1500000"

// Market data (when available)
$price = $token->getCurrentPrice();
$marketCap = $token->getMarketCap();

// Token holders and analytics
$holders = $token->getHolders();
$circulatingSupply = $token->getTotalCirculatingSupply();
```

### NFT Collections (ERC-721 & ERC-1155)

#### Collection Creation & Management

```php
// Create an NFT collection
$collection = NftCollection::create([
    'contract_id' => $contract->id,
    'name' => 'Bored Ape Yacht Club',
    'symbol' => 'BAYC',
    'description' => 'A collection of 10,000 unique Bored Ape NFTs',
    'image_url' => 'https://example.com/bayc-logo.png',
    'banner_url' => 'https://example.com/bayc-banner.png',
    'external_url' => 'https://boredapeyachtclub.com',
    'standard' => TokenType::ERC721,
    'total_supply' => '10000',
    'floor_price' => '50000000000000000000', // 50 ETH in wei
    'metadata' => [
        'creator' => 'Yuga Labs',
        'royalty_fee' => '2.5%',
        'launch_date' => '2021-04-23',
    ],
]);

// Collection analytics and information
echo $collection->getOwnerCount();           // Number of unique owners
echo $collection->getUniqueTokenCount();     // Number of minted tokens
echo $collection->getFloorPriceFormatted(); // "50.0 ETH"

// Check collection capabilities
if ($collection->supportsSemiFungible()) {
    // ERC-1155 collection supports quantities
}

// Get collection statistics
$stats = $collection->getCollectionStats();
/*
[
    'total_supply' => '10000',
    'unique_owners' => 5234,
    'floor_price' => '50000000000000000000',
    'volume_24h' => '1250000000000000000000',
    'transfers_24h' => 45
]
*/
```

#### Trait & Rarity Management

```php
// Analyze trait distribution across collection
$traitDistribution = $collection->getTraitDistribution();
/*
[
    'Background' => [
        'Blue' => ['count' => 1250, 'percentage' => 12.5],
        'Red' => ['count' => 890, 'percentage' => 8.9],
        // ...
    ],
    'Eyes' => [
        'Laser' => ['count' => 45, 'percentage' => 0.45],
        // ...
    ]
]
*/

// Get rarity rankings
$rarityRankings = $collection->getRarityRanking(10); // Top 10 rarest
```

### NFT Ownership & Wallet Management

#### Direct NFT Ownership

```php
// Create NFT ownership record
$walletNft = WalletNft::create([
    'wallet_id' => $wallet->id,
    'nft_collection_id' => $collection->id,
    'token_id' => '1234',
    'quantity' => '1', // Always 1 for ERC-721, can be >1 for ERC-1155
    'metadata_uri' => 'ipfs://QmHash/metadata.json',
    'metadata' => [
        'name' => 'Bored Ape #1234',
        'description' => 'A unique Bored Ape with rare traits',
        'image' => 'ipfs://QmHash/image.png',
        'attributes' => [
            ['trait_type' => 'Background', 'value' => 'Blue'],
            ['trait_type' => 'Eyes', 'value' => 'Laser'],
            ['trait_type' => 'Mouth', 'value' => 'Bored'],
        ],
    ],
    'traits' => [
        'Background' => 'Blue',
        'Eyes' => 'Laser',
        'Mouth' => 'Bored',
    ],
    'rarity_rank' => 45, // Based on trait rarity
    'acquired_at' => now(),
]);

// NFT information and display
echo $walletNft->getDisplayName();    // "Bored Ape #1234"
echo $walletNft->getName();           // From metadata or auto-generated
echo $walletNft->getCollectionName(); // "Bored Ape Yacht Club"

// Semi-fungible token support (ERC-1155)
if ($walletNft->isSemiFungible()) {
    echo $walletNft->canTransferQuantity('5'); // Check if can transfer 5 units
}

// Metadata and rarity
$metadata = $walletNft->getMetadata();
$traits = $walletNft->getTraits();
$rarityScore = $walletNft->getRarityScore();

// Check if metadata needs refresh
if ($walletNft->needsMetadataRefresh()) {
    $walletNft->refreshMetadata();
}
```

#### Wallet NFT Portfolio Management

```php
// Get wallet's complete NFT portfolio
$wallet = Wallet::find(1);

// Basic NFT relationships
$nfts = $wallet->nfts()->get();                    // All owned NFTs
$collections = $wallet->nftCollections()->get();   // All collections owned
$recentNfts = $wallet->recentNfts(10)->get();     // Recently acquired

// Portfolio analytics
echo $wallet->getNftCount();                // Total NFT count
echo $wallet->getUniqueCollectionCount();   // Number of different collections

// Check specific ownership
if ($wallet->ownsNft($collection, '1234')) {
    echo "Wallet owns token #1234 from this collection";
}

// Get NFT gallery for display
$gallery = $wallet->getNftGallery();
/*
Collection of NFTs with metadata optimized for gallery display:
[
    'id' => 1,
    'collection_name' => 'Bored Ape Yacht Club',
    'token_id' => '1234',
    'name' => 'Bored Ape #1234',
    'image' => 'ipfs://...',
    'rarity_rank' => 45,
    // ...
]
*/

// Get NFTs by collection
$baycNfts = $wallet->getNftsByCollection($collection);
```

### Token Type Enumeration & Standards

```php
use Roberts\Web3Laravel\Enums\TokenType;

// Unified token type enum for all standards
$tokenTypes = [
    TokenType::ERC20,   // Fungible tokens
    TokenType::ERC721,  // Non-fungible tokens
    TokenType::ERC1155, // Multi-token (semi-fungible)
];

// Type checking and capabilities
echo TokenType::ERC20->getDisplayName();      // "ERC-20 (Fungible Token)"
echo TokenType::ERC721->isNft();              // true
echo TokenType::ERC1155->isSemiFungible();    // true
echo TokenType::ERC1155->supportsQuantity();  // true

// Use in models
$collection = NftCollection::where('standard', TokenType::ERC721)->first();
$multiTokens = NftCollection::where('standard', TokenType::ERC1155)->get();
```

### Service Integration & Blockchain Operations

All token and NFT operations integrate seamlessly with the existing transaction pipeline and services:

```php
use Roberts\Web3Laravel\Services\TokenService;

$tokenService = app(TokenService::class);

// Token operations (ERC-20 only now)
$balance = $tokenService->balanceOf($token, $wallet->address);
$transaction = $tokenService->transfer($token, $fromWallet, $toAddress, $amount);
$transaction = $tokenService->mint($token, $minterWallet, $toAddress, $amount);

// All operations return Transaction models for async processing
echo $transaction->status; // 'pending' -> 'submitted' -> 'confirmed'
```

### Factory Support for Testing

Comprehensive factory support for all models with realistic data generation:

```php
// Create test tokens with proper metadata
$token = Token::factory()
    ->withSymbol('TEST')
    ->withDeployerMetadata()
    ->create();

// Create NFT collections with analytics
$collection = NftCollection::factory()
    ->erc721()
    ->withFloorPrice('50000000000000000000') // 50 ETH
    ->create();

// Create NFT ownership with specific traits
$walletNft = WalletNft::factory()
    ->for($wallet)
    ->for($collection)
    ->withTokenId('1234')
    ->rare() // High rarity traits
    ->create();
```

### Artisan Commands

Convenient CLI commands for token and NFT operations:

```bash
# Token operations
php artisan web3:token:balance 1 0x742d35Cc... --format
php artisan web3:token:info 1
php artisan web3:token:transfer 1 wallet_id 0xRecipient... 1000

# NFT operations  
php artisan web3:nft:info collection_id token_id
php artisan web3:nft:transfer wallet_id collection_id token_id 0xRecipient...
php artisan web3:nft:refresh-metadata collection_id token_id
```

This architecture provides clean separation of concerns, comprehensive analytics capabilities, and seamless integration with deployment platforms while maintaining full compatibility with existing Laravel patterns and the async transaction system.

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
