<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Roberts\Web3Laravel\Enums\TransactionStatus;
use Roberts\Web3Laravel\Events\TransactionRequested;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int|null $blockchain_id
 * @property int|null $contract_id
 * @property string|null $from
 * @property string|null $to
 * @property string|null $value
 * @property string|null $data
 * @property int|null $gas_limit
 * @property string|null $gwei
 * @property string|null $fee_max
 * @property string|null $priority_max
 * @property bool|null $is_1559
 * @property int|null $nonce
 * @property int|null $chain_id
 * @property array|null $function_params
 * @property array|null $meta
 * @property TransactionStatus|string $status
 * @property string|null $error
 * @property string|null $tx_hash
 * @property-read Wallet $wallet
 */
class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $guarded = ['id'];

    protected $casts = [
        'wallet_id' => 'integer',
        'blockchain_id' => 'integer',
        'contract_id' => 'integer',
        'value' => 'string',           // wei
        'token_quantity' => 'string',  // wei
        'gas_limit' => 'integer',
        'gwei' => 'string',            // gasPrice in wei (legacy)
        'fee_max' => 'string',         // maxFeePerGas in wei
        'priority_max' => 'string',    // maxPriorityFeePerGas in wei
        'is_1559' => 'boolean',
        'nonce' => 'integer',
        'chain_id' => 'integer',
        'function_params' => 'array',
        'meta' => 'array',
        'status' => TransactionStatus::class,
    ];

    protected $dispatchesEvents = [
        'created' => TransactionRequested::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $tx) {
            /** @var Wallet|null $wallet */
            $wallet = $tx->wallet ?? $tx->wallet()->first();
            $isEvm = $wallet instanceof Wallet && $wallet->protocol->isEvm();

            // Only perform gas estimation on EVM
            if ($isEvm && empty($tx->gas_limit)) {
                /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
                $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);
                $estimateHex = $svc->estimateGas($wallet, array_filter([
                    'to' => $tx->to,
                    'value' => $tx->value,
                    'data' => $tx->data,
                ], fn ($v) => $v !== null && $v !== ''));
                $gasInt = is_string($estimateHex) && str_starts_with($estimateHex, '0x')
                    ? hexdec(substr($estimateHex, 2))
                    : (int) $estimateHex;
                // Add a small safety margin (12%)
                $tx->gas_limit = (int) ceil($gasInt * 1.12);
            }

            // Only set EIP-1559 defaults for EVM
            if ($isEvm) {
                if ($tx->is_1559 === null) {
                    $tx->is_1559 = true;
                }
                if ($tx->is_1559 && (empty($tx->priority_max) || empty($tx->fee_max))) {
                    /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
                    $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);
                    $fees = $svc->suggestFees($wallet);
                    if (empty($tx->priority_max)) {
                        $tx->priority_max = $fees['priority'];
                    }
                    if (empty($tx->fee_max)) {
                        $tx->fee_max = $fees['max'];
                    }
                }
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function blockchain(): BelongsTo
    {
        return $this->belongsTo(Blockchain::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    // Convenience status helpers
    public function statusValue(): string
    {
        return is_string($this->status) ? $this->status : ($this->status->value ?? (string) ($this->status ?? ''));
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::Pending || $this->statusValue() === TransactionStatus::Pending->value;
    }

    public function isPreparing(): bool
    {
        return $this->status === TransactionStatus::Preparing || $this->statusValue() === TransactionStatus::Preparing->value;
    }

    public function isPrepared(): bool
    {
        return $this->status === TransactionStatus::Prepared || $this->statusValue() === TransactionStatus::Prepared->value;
    }

    public function isSubmitted(): bool
    {
        return $this->status === TransactionStatus::Submitted || $this->statusValue() === TransactionStatus::Submitted->value;
    }

    public function isConfirmed(): bool
    {
        return $this->status === TransactionStatus::Confirmed || $this->statusValue() === TransactionStatus::Confirmed->value;
    }

    public function isFailed(): bool
    {
        return $this->status === TransactionStatus::Failed || $this->statusValue() === TransactionStatus::Failed->value;
    }

    // Transition helpers
    public function markFailed(string $reason): void
    {
        $this->update([
            'status' => TransactionStatus::Failed,
            'error' => trim(($this->error ? $this->error.' ' : '').$reason),
        ]);
    }
}
