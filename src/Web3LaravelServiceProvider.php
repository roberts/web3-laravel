<?php

namespace Roberts\Web3Laravel;

use Roberts\Web3Laravel\Commands\Web3LaravelCommand;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Services\TransactionService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class Web3LaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
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
            ])
            ->hasCommands([
                Web3LaravelCommand::class,
                \Roberts\Web3Laravel\Commands\WalletCreateCommand::class,
                \Roberts\Web3Laravel\Commands\WalletListCommand::class,
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Web3Laravel::class, function ($app) {
            return new Web3Laravel(config('web3-laravel'));
        });

        $this->app->singleton(ContractCaller::class, function ($app) {
            return new ContractCaller($app->make(Web3Laravel::class));
        });

        $this->app->singleton(TransactionService::class, function ($app) {
            return new TransactionService($app->make(Web3Laravel::class));
        });
    }
}
