<?php

namespace Roberts\Web3Laravel\Console\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Jobs\ConfirmTransaction;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;

class WatchConfirmationsCommand extends Command
{
    protected $signature = 'web3:watch-confirmations {--chainId=} {--interval=5}';

    protected $description = 'Track transaction confirmations by polling blockNumber and dispatching confirm jobs.';

    public function handle()
    {
        $interval = (int) ($this->option('interval') ?? config('web3-laravel.confirmations_poll_interval', 10));
        $this->info('Starting confirmations watcher (polling)...');
        $lastBlock = null;

        $iterations = 0;
        while (true) {
            try {
                /** @var EvmClientInterface $evm */
                $evm = app(EvmClientInterface::class);
                $lastBlock = $evm->blockNumber();
            } catch (\Throwable) {
                $lastBlock = null;
            }

            if ($lastBlock) {
                // Trigger confirm checks for submitted txs
                $ids = Transaction::query()
                    ->where('status', 'submitted')
                    ->pluck('id');
                foreach ($ids as $id) {
                    ConfirmTransaction::dispatch($id);
                }
            }

            sleep($interval);
            if (app()->runningUnitTests() && ++$iterations > 3) {
                break; // prevent infinite loop during analysis/tests
            }
        }

        // Long-running command; no return needed
    }
}
