<?php

namespace Roberts\Web3Laravel;

use Roberts\Web3Laravel\Commands\Web3LaravelCommand;
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
            ->hasMigration('create_web3_laravel_table')
            ->hasCommand(Web3LaravelCommand::class);
    }
}
