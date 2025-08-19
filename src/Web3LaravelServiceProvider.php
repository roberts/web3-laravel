<?php

namespace Roberts\Web3Laravel;

use Roberts\Web3Laravel\Commands\Web3LaravelCommand;
use Roberts\Web3Laravel\Core\Provider\Endpoint;
use Roberts\Web3Laravel\Core\Provider\Pool as ProviderPool;
use Roberts\Web3Laravel\Core\Rpc\PooledHttpClient;
use Roberts\Web3Laravel\Protocols\Bitcoin\BitcoinProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Cardano\CardanoProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Protocols\Evm\EvmJsonRpcClient;
use Roberts\Web3Laravel\Protocols\Evm\EvmProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Hedera\HederaProtocolAdapter;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Protocols\Solana\SolanaJsonRpcClient;
use Roberts\Web3Laravel\Protocols\Solana\SolanaProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Solana\SolanaService as ProtocolSolanaService;
use Roberts\Web3Laravel\Protocols\Solana\SolanaSigner;
use Roberts\Web3Laravel\Protocols\Sui\SuiProtocolAdapter;
use Roberts\Web3Laravel\Protocols\Xrpl\XrplProtocolAdapter;
use Roberts\Web3Laravel\Services\BalanceService;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Services\KeyReleaseService;
use Roberts\Web3Laravel\Services\Keys\KeyEngineInterface;
use Roberts\Web3Laravel\Services\Keys\NativeKeyEngine;
use Roberts\Web3Laravel\Services\TokenService;
use Roberts\Web3Laravel\Services\TransactionService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class Web3LaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('web3-laravel')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_blockchains_table',
                'create_wallets_table',
                'create_contracts_table',
                'create_tokens_table',
                'create_transactions_table',
                'create_key_releases_table',
                'create_wallet_tokens_table',
            ])
            ->hasCommands([
                Web3LaravelCommand::class,
                \Roberts\Web3Laravel\Commands\WalletCreateCommand::class,
                \Roberts\Web3Laravel\Commands\WalletListCommand::class,
                \Roberts\Web3Laravel\Commands\WalletTypeCommand::class,
                \Roberts\Web3Laravel\Commands\TokenBalanceCommand::class,
                \Roberts\Web3Laravel\Commands\TokenInfoCommand::class,
                \Roberts\Web3Laravel\Commands\TokenMintCommand::class,
                \Roberts\Web3Laravel\Commands\TokenTransferCommand::class,
                \Roberts\Web3Laravel\Commands\TokenApproveCommand::class,
                \Roberts\Web3Laravel\Commands\NativeTransferCommand::class,
                \Roberts\Web3Laravel\Console\Commands\WatchConfirmationsCommand::class,
                \Roberts\Web3Laravel\Commands\WalletTokenSnapshotCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        // Removed Web3Laravel (web3.php) manager; native stack only.

        $this->app->singleton(ContractCaller::class, function ($app) {
            return new ContractCaller($app->make(EvmClientInterface::class));
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService;
        });

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(
                $app->make(ContractCaller::class),
                $app->make(TransactionService::class)
            );
        });

        $this->app->singleton(\Roberts\Web3Laravel\Services\WalletTokenService::class, function ($app) {
            return new \Roberts\Web3Laravel\Services\WalletTokenService(
                $app->make(TokenService::class),
                $app->make(BalanceService::class)
            );
        });

        $this->app->singleton(KeyReleaseService::class, function ($app) {
            return new KeyReleaseService;
        });

        // SolanaService removed in favor of protocol adapter usage

        $this->app->singleton(BalanceService::class, function ($app) {
            return new BalanceService($app->make(\Roberts\Web3Laravel\Protocols\ProtocolRouter::class));
        });

        // Protocol-scoped Solana service (optional facade around adapter)
        $this->app->singleton(ProtocolSolanaService::class, function ($app) {
            return new ProtocolSolanaService($app->make(\Roberts\Web3Laravel\Protocols\ProtocolRouter::class));
        });

        // Solana protocol adapter
        $this->app->singleton(SolanaSigner::class, function ($app) {
            return new SolanaSigner;
        });

        $this->app->singleton(SolanaProtocolAdapter::class, function ($app) {
            return new SolanaProtocolAdapter(
                $app->make(SolanaJsonRpcClient::class),
                $app->make(SolanaSigner::class)
            );
        });

        // EVM protocol adapter
        $this->app->singleton(EvmProtocolAdapter::class, function ($app) {
            return new EvmProtocolAdapter($app->make(EvmClientInterface::class));
        });

        // Bind native EVM client (web3.php fully removed)
        $this->app->bind(EvmClientInterface::class, function ($app) {
            $timeout = (int) config('web3-laravel.request_timeout', 10);
            $retries = (int) data_get(config('web3-laravel.rpc'), 'retries', 2);
            $backoff = (int) data_get(config('web3-laravel.rpc'), 'backoff_ms', 200);
            $headers = (array) data_get(config('web3-laravel.rpc'), 'headers', []);
            $rpcs = (array) config('web3-laravel.networks');
            $default = (string) config('web3-laravel.default_rpc');
            $endpoints = [];
            if (empty($rpcs)) {
                $endpoints[] = new Endpoint($default, 1, $headers);
            } else {
                foreach ($rpcs as $cid => $url) {
                    if (is_string($url)) {
                        $endpoints[] = new Endpoint($url, 1, $headers);
                    }
                }
            }
            $pool = new ProviderPool($endpoints);
            $rpc = new PooledHttpClient($pool, $timeout, $retries, $backoff, $headers);

            return new EvmJsonRpcClient($rpc);
        });

        // Bind Solana JSON-RPC client using the same pooled HTTP client
        $this->app->singleton(SolanaJsonRpcClient::class, function ($app) {
            $timeout = (int) config('web3-laravel.request_timeout', 10);
            $retries = (int) data_get(config('web3-laravel.rpc'), 'retries', 2);
            $backoff = (int) data_get(config('web3-laravel.rpc'), 'backoff_ms', 200);
            $headers = (array) data_get(config('web3-laravel.rpc'), 'headers', []);
            $default = (string) data_get(config('web3-laravel.solana'), 'default_rpc', 'https://api.mainnet-beta.solana.com');

            $endpoints = [new Endpoint($default, 1, $headers)];
            $pool = new ProviderPool($endpoints);
            $rpc = new PooledHttpClient($pool, $timeout, $retries, $backoff, $headers);

            return new SolanaJsonRpcClient($rpc);
        });

        // Protocol router to dispatch to the right adapter
        $this->app->singleton(ProtocolRouter::class, function ($app) {
            $router = new ProtocolRouter;
            $router->register($app->make(EvmProtocolAdapter::class));
            $router->register($app->make(SolanaProtocolAdapter::class));
            // Adapters with KeyEngine dependency
            $app->singleton(BitcoinProtocolAdapter::class, fn ($app) => new BitcoinProtocolAdapter($app->make(KeyEngineInterface::class)));
            $app->singleton(SuiProtocolAdapter::class, fn ($app) => new SuiProtocolAdapter($app->make(KeyEngineInterface::class)));
            $app->singleton(XrplProtocolAdapter::class, fn ($app) => new XrplProtocolAdapter($app->make(KeyEngineInterface::class)));
            $router->register($app->make(BitcoinProtocolAdapter::class));
            $router->register($app->make(SuiProtocolAdapter::class));
            $router->register($app->make(XrplProtocolAdapter::class));
            $router->register($app->make(CardanoProtocolAdapter::class));
            $router->register($app->make(HederaProtocolAdapter::class));

            return $router;
        });

        // Key engine binding
        $this->app->singleton(KeyEngineInterface::class, function () {
            return new NativeKeyEngine;
        });

        // Register event service provider for package
        $this->app->register(\Roberts\Web3Laravel\Providers\EventServiceProvider::class);
    }
}
