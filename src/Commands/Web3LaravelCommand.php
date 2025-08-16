<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;

class Web3LaravelCommand extends Command
{
    public $signature = 'web3-laravel';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
