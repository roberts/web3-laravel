<?php

namespace Roberts\Web3Laravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Roberts\Web3Laravel\Web3LaravelServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Roberts\\Web3Laravel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Load and run package migrations for tests
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
    }

    protected function getPackageProviders($app)
    {
        return [
            Web3LaravelServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        // Use sqlite in-memory for tests
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Encryption key for Crypt
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // No need to run migrator here; done in setUp
    }
}
