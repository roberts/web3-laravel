<?php

namespace Roberts\Web3Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Events\TransactionConfirmed;
use Roberts\Web3Laravel\Models\Transaction;

class ConfirmTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $transactionId)
    {
        // Delay between polls can be configured at dispatch time
    }

    public function handle(): void
    {
        /** @var Transaction|null $tx */
        $tx = Transaction::query()->find($this->transactionId);
        if (! $tx || empty($tx->tx_hash)) {
            return;
        }

    /** @var \Roberts\Web3Laravel\Models\Wallet|null $wallet */
    $wallet = $tx->wallet ?? $tx->wallet()->first();
        if (! $wallet) {
            return;
        }
    /** @var \Roberts\Web3Laravel\Web3Laravel $manager */
    $manager = app(\Roberts\Web3Laravel\Web3Laravel::class);
    $eth = $manager->ethFrom($wallet->web3());

        // Get receipt
        try {
            $receipt = $manager->ethCall($eth, 'getTransactionReceipt', [$tx->tx_hash]);
        } catch (\Throwable) {
            return; // try again later
        }

        // No receipt yet; re-dispatch with a small delay
        if (! $receipt) {
            static::dispatch($tx->id)->delay(now()->addSeconds(10));

            return;
        }

        // Determine confirmations
    $currentBlock = $manager->ethCall($eth, 'blockNumber');
        if (empty($receipt->blockNumber) || $currentBlock === null) {
            static::dispatch($tx->id)->delay(now()->addSeconds(10));

            return;
        }

        $blockNum = is_string($receipt->blockNumber) && str_starts_with($receipt->blockNumber, '0x')
            ? hexdec(substr($receipt->blockNumber, 2))
            : (int) $receipt->blockNumber;
        $head = is_string($currentBlock) && str_starts_with($currentBlock, '0x')
            ? hexdec(substr($currentBlock, 2))
            : (int) $currentBlock;

        $confirmations = max(0, $head - $blockNum + 1);
        $required = (int) config('web3-laravel.confirmations_required', 6);

        if ($confirmations >= $required) {
            $tx->update([
                'status' => \Roberts\Web3Laravel\Enums\TransactionStatus::Confirmed,
                'meta' => array_merge((array) $tx->meta, [
                    'confirmations' => $confirmations,
                    'receipt' => $receipt,
                ]),
            ]);
            event(new TransactionConfirmed($tx->fresh()));

            return;
        }

        // Not enough confirmations; reschedule
        static::dispatch($tx->id)->delay(now()->addSeconds(10));
    }
}
