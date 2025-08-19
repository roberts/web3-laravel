<?php

namespace Roberts\Web3Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Roberts\Web3Laravel\Events\TransactionConfirmed;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;

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
        /** @var EvmClientInterface $evm */
        $evm = app(EvmClientInterface::class);
        // Get receipt
        try {
            $receipt = $evm->getTransactionReceipt($tx->tx_hash);
        } catch (\Throwable) {
            return; // try again later
        }

        // No receipt yet; re-dispatch with a small delay
        if (! $receipt) {
            static::dispatch($tx->id)->delay(now()->addSeconds(10));

            return;
        }

        // Determine confirmations
        $currentBlock = $evm->blockNumber();
        $receiptBlock = $receipt['blockNumber'] ?? null;
        if (empty($receiptBlock) || $currentBlock === null) {
            static::dispatch($tx->id)->delay(now()->addSeconds(10));

            return;
        }

        $blockNum = is_string($receiptBlock) && str_starts_with($receiptBlock, '0x')
            ? hexdec(substr($receiptBlock, 2))
            : (int) $receiptBlock;
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
