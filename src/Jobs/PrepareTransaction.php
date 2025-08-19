<?php

namespace Roberts\Web3Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Events\TransactionFailed;
use Roberts\Web3Laravel\Events\TransactionPrepared;
use Roberts\Web3Laravel\Events\TransactionPreparing;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Services\TransactionService;
use Roberts\Web3Laravel\Support\Hex;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Protocols\Contracts\ProtocolTransactionAdapter;
use Roberts\Web3Laravel\Protocols\CostEstimatorRouter;
use Roberts\Web3Laravel\Services\BalanceService;

class PrepareTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $transactionId) {}

    public function handle(TransactionService $svc): void
    {
        /** @var Transaction|null $tx */
        $tx = Transaction::query()->find($this->transactionId);
        if (! $tx) {
            return;
        }

        // Stage
        $tx->update(['status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Preparing]);
        event(new TransactionPreparing($tx->fresh()));

        /** @var \Roberts\Web3Laravel\Models\Wallet|null $wallet */
        $wallet = $tx->wallet ?? $tx->wallet()->first();
        if (! $wallet) {
            $tx->update(['status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Failed, 'error' => 'wallet_not_found']);
            event(new TransactionFailed($tx->fresh(), 'wallet_not_found'));

            return;
        }

        // Delegate protocol-specific preparation
        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        /** @var ProtocolTransactionAdapter $txAdapter */
        $txAdapter = $router->for($wallet->protocol);
        if ($txAdapter instanceof ProtocolTransactionAdapter) {
            try { $txAdapter->prepareTransaction($tx, $wallet); } catch (\Throwable) {}
        }

        // Estimate cost via per-protocol estimator and perform balance check generically
        try {
            /** @var CostEstimatorRouter $costRouter */
            $costRouter = app(CostEstimatorRouter::class);
            $estimator = $costRouter->for($wallet->protocol);
            $result = $estimator->estimateAndPopulate($tx, $wallet);

            // Best-effort balance check (skip in unit tests to avoid network requirements)
            if (! app()->runningUnitTests()) {
                /** @var BalanceService $balanceSvc */
                $balanceSvc = app(BalanceService::class);
                $balance = $balanceSvc->native($wallet); // decimal string in smallest unit
                $required = (string) ($result['total_required'] ?? '0');
                if ($this->decCompare($balance, $required) < 0) {
                    $tx->update([
                        'status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Failed,
                        'error' => trim(($tx->error ? $tx->error.' ' : '').'insufficient_funds'),
                    ]);
                    event(new TransactionFailed($tx->fresh(), 'insufficient_funds'));

                    return;
                }
            }
        } catch (\Throwable $e) {
            // If estimation fails, continue; downstream may still handle or fail on submit
        }

        // Persist any computed fields and advance
        $tx->save();
        $tx->update(['status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Prepared]);
        event(new TransactionPrepared($tx->fresh()));

        SubmitTransaction::dispatch($tx->id);
    }

    /** Compare two non-negative decimal strings (a<b => -1, a==b => 0, a>b => 1). */
    protected function decCompare(string $a, string $b): int
    {
        $a = ltrim($a, '+'); $b = ltrim($b, '+');
        $a = ltrim($a, '0'); $b = ltrim($b, '0');
        $la = strlen($a); $lb = strlen($b);
        if ($la !== $lb) { return $la < $lb ? -1 : 1; }
        if ($a === '' && $b === '') { return 0; }
        return $a === $b ? 0 : ($a < $b ? -1 : 1);
    }
}
