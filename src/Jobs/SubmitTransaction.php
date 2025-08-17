<?php

namespace Roberts\Web3Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Events\TransactionFailed;
use Roberts\Web3Laravel\Events\TransactionSubmitted;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Services\TransactionService;

class SubmitTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $transactionId) {}

    public function handle(TransactionService $tx): void
    {
        /** @var Transaction|null $model */
        $model = Transaction::query()->find($this->transactionId);
        if (! $model) {
            return;
        }

        $payload = [
            'to' => $model->to,
            'value' => $model->value,
            'data' => $model->data,
            'gas' => $model->gas_limit,
            'nonce' => $model->nonce,
            'chainId' => $model->chain_id,
        ];

        if ($model->is_1559) {
            $payload['maxFeePerGas'] = $model->fee_max;
            $payload['maxPriorityFeePerGas'] = $model->priority_max;
            if (! empty($model->access_list)) {
                $payload['accessList'] = json_decode($model->access_list, true) ?: [];
            }
        } else {
            $payload['gasPrice'] = $model->gwei;
        }

        try {
            $hash = $tx->sendRaw($model->wallet, $payload);
            $model->update([
                'tx_hash' => $hash,
                'status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Submitted,
            ]);
            event(new TransactionSubmitted($model->fresh()));
            // Kick off confirmation polling
            \Roberts\Web3Laravel\Jobs\ConfirmTransaction::dispatch($model->id)->delay(now()->addSeconds(10));
        } catch (\Throwable $e) {
            $model->update([
                'status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Failed,
                'error' => $e->getMessage(),
            ]);
            event(new TransactionFailed($model->fresh(), $e->getMessage() ?: 'submission_failed'));
        }
    }
}
