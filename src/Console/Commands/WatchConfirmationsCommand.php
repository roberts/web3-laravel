<?php

namespace Roberts\Web3Laravel\Console\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Jobs\ConfirmTransaction;
use Roberts\Web3Laravel\Models\Transaction;

class WatchConfirmationsCommand extends Command
{
    protected $signature = 'web3:watch-confirmations {--chainId=} {--interval=5}';

    protected $description = 'Track transaction confirmations by dispatching confirm jobs for submitted transactions.';

    public function handle()
    {
        $interval = (int) ($this->option('interval') ?? config('web3-laravel.confirmations_poll_interval', 10));
        $this->info('Starting confirmations watcher (polling)...');
        $iterations = 0;
        while (true) {
            // Trigger confirm checks for submitted txs across all protocols
            $ids = Transaction::query()
                ->where('status', 'submitted')
                ->pluck('id');
            foreach ($ids as $id) {
                ConfirmTransaction::dispatch($id);
            }

            sleep($interval);
            if (app()->runningUnitTests() && ++$iterations > 3) {
                break; // prevent infinite loop during analysis/tests
            }
        }

        // Long-running command; no return needed
    }
}
