<?php

namespace Roberts\Web3Laravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Roberts\Web3Laravel\Commands\Web3LaravelCommand;
use Roberts\Web3Laravel\Web3Laravel;

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
            ])
            ->hasCommand(Web3LaravelCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(Web3Laravel::class, function ($app) {
            return new Web3Laravel(config('web3-laravel'));
        });
    }
}
