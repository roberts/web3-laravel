<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Roberts\Web3Laravel\Events\TransactionRequested;

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
    ];

    protected $dispatchesEvents = [
        'created' => TransactionRequested::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (Transaction $tx) {
            // If gas_limit not provided, estimate it
            if (empty($tx->gas_limit)) {
                /** @var \Roberts\Web3Laravel\Services\TransactionService $svc */
                $svc = app(\Roberts\Web3Laravel\Services\TransactionService::class);
                $wallet = $tx->wallet ?? $tx->wallet()->first();
                if ($wallet) {
                    $estimateHex = $svc->estimateGas($wallet, array_filter([
                        'to' => $tx->to,
                        'value' => $tx->value,
                        'data' => $tx->data,
                    ], fn ($v) => $v !== null && $v !== '' && $v !== 0));
                    $gasInt = is_string($estimateHex) && str_starts_with($estimateHex, '0x')
                        ? hexdec(substr($estimateHex, 2))
                        : (int) $estimateHex;
                    // Add a small safety margin (12%)
                    $tx->gas_limit = (int) ceil($gasInt * 1.12);
                }
            }

            // Default to EIP-1559 and populate fees if not given
            if ($tx->is_1559 === null) {
                $tx->is_1559 = true;
            }
            if ($tx->is_1559) {
                if (empty($tx->priority_max) || empty($tx->fee_max)) {
                    $wallet = $wallet ?? ($tx->wallet ?? $tx->wallet()->first());
                    if ($wallet) {
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
}
