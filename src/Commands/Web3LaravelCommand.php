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

        $web3 = $manager->web3($chainId, $rpc ?: null);
        $web3->clientVersion(function ($err, $version) {
            if ($err !== null) {
                $this->error('Error: '.$err->getMessage());

                return;
            }
            $this->info('Client version: '.(string) $version);
        });

        return self::SUCCESS;
    }
}
