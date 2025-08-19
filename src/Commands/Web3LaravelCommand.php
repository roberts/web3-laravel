<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;

class Web3LaravelCommand extends Command
{
    public $signature = 'web3:ping {--chainId=} {--rpc=}';

    public $description = 'Simple ping: prints current block number via configured EVM RPC.';

    public function handle(): int
    {
        try {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            $this->info('Block number: '.$evm->blockNumber());
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
