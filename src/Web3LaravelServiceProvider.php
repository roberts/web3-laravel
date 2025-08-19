<?php

namespace Roberts\Web3Laravel;

use Roberts\Web3Laravel\Commands\Web3LaravelCommand;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Core\Rpc\HttpClient as NativeHttpRpc;
use Roberts\Web3Laravel\Core\Rpc\PooledHttpClient;
use Roberts\Web3Laravel\Core\Provider\Endpoint;
use Roberts\Web3Laravel\Core\Provider\Pool as ProviderPool;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Protocols\Evm\EvmJsonRpcClient;
use Roberts\Web3Laravel\Services\KeyReleaseService;
use Roberts\Web3Laravel\Services\SolanaService;
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
                'create_web3_laravel_table',
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
                \Roberts\Web3Laravel\Console\Commands\WatchConfirmationsCommand::class,
                \Roberts\Web3Laravel\Commands\WalletTokenSnapshotCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Web3Laravel::class, function ($app) {
            return new Web3Laravel(config('web3-laravel'));
        });

        $this->app->singleton(ContractCaller::class, function ($app) {
            return new ContractCaller($app->make(EvmClientInterface::class));
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(Web3Laravel::class));
        });

        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService(
                $app->make(ContractCaller::class),
                $app->make(TransactionService::class)
            );
        });

        $this->app->singleton(\Roberts\Web3Laravel\Services\WalletTokenService::class, function ($app) {
            return new \Roberts\Web3Laravel\Services\WalletTokenService(
                $app->make(TokenService::class)
            );
        });

        $this->app->singleton(KeyReleaseService::class, function ($app) {
            return new KeyReleaseService;
        });

        $this->app->singleton(SolanaService::class, function ($app) {
            return new SolanaService;
        });

        // Driver binding for EVM client
        $this->app->bind(EvmClientInterface::class, function ($app) {
            $driver = config('web3-laravel.driver', 'web3php');
            if ($driver === 'native') {
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
            }
            // Fallback (web3php): use adapter backed by current Web3 manager
            $endpoint = config('web3-laravel.default_rpc');
            $timeout = (int) config('web3-laravel.request_timeout', 10);
            return new \Roberts\Web3Laravel\Protocols\Evm\Drivers\Web3PhpAdapter($app->make(\Roberts\Web3Laravel\Web3Laravel::class));
        });

        // Register event service provider for package
        $this->app->register(\Roberts\Web3Laravel\Providers\EventServiceProvider::class);
    }
}
