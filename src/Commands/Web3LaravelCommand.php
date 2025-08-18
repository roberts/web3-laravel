<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Web3Laravel;

class Web3LaravelCommand extends Command
{
    public $signature = 'web3:ping {--chainId=} {--rpc=}';

    public $description = 'Show the resolved RPC and try web3_clientVersion.';

    public function handle(): int
    {
        /** @var Web3Laravel $manager */
        $manager = app(Web3Laravel::class);
        $chainId = $this->option('chainId');
        $chainId = is_numeric($chainId) ? (int) $chainId : null;
        $rpc = $this->option('rpc');

        $rpcUrl = $manager->resolveRpcUrl($chainId);
        $this->info('RPC: '.$rpcUrl);

        try {
            $version = $manager->clientVersionString($chainId, $rpc ?: null);
            $this->info('Client version: '.$version);
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
