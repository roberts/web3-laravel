<?php

namespace Roberts\Web3Laravel\Console\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Jobs\ConfirmTransaction;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Web3Laravel;

class WatchConfirmationsCommand extends Command
{
    protected $signature = 'web3:watch-confirmations {--chainId=} {--interval=5}';

    protected $description = 'Track transaction confirmations using WebSocket (or fallback polling) and update DB records to confirmed.';

    public function handle(Web3Laravel $manager)
    {
        $mode = config('web3-laravel.confirmations_mode', 'polling');
        $interval = (int) ($this->option('interval') ?? config('web3-laravel.confirmations_poll_interval', 10));
        $chainId = $this->option('chainId') ? (int) $this->option('chainId') : null;

        if ($mode !== 'websocket') {
            $this->info('confirmations_mode is not websocket; falling back to periodic polling.');
        }

        $web3 = $mode === 'websocket' ? $manager->web3Ws($chainId) : $manager->web3($chainId);
        $eth = $manager->ethFrom($web3);

        $this->info('Starting confirmations watcher (mode='.$mode.')...');
        $lastBlock = null;

        // Subscribe if possible (event-driven), else poll blockNumber
        $supportsSub = method_exists($eth, 'subscribe');

        if ($supportsSub && $mode === 'websocket') {
            // Use newHeads subscription if available
            try {
                $eth->subscribe('newHeads', function ($err, $block) use (&$lastBlock) {
                    if ($err) {
                        return;
                    }
                    $lastBlock = $block;
                });
            } catch (\Throwable $e) {
                $supportsSub = false;
                $this->warn('Subscription failed; falling back to polling: '.$e->getMessage());
            }
        }

        $iterations = 0;
        while (true) {
            if (! $supportsSub) {
                try {
                    $lastBlock = $manager->ethCall($eth, 'blockNumber');
                } catch (\Throwable) {
                    $lastBlock = null;
                }
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
