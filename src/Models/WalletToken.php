<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot-like model representing a wallet's ERC-20 balance snapshot for a token.
 *
 * @property int $id
 * @property int $wallet_id
 * @property int $token_id
 * @property string $balance Raw integer balance (no decimals applied)
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 * @property-read Wallet $wallet
 * @property-read Token $token
 */
class WalletToken extends Model
{
    use HasFactory;

    protected $table = 'wallet_tokens';

    protected $guarded = ['id'];

    protected $casts = [
        'wallet_id' => 'integer',
        'token_id' => 'integer',
        'balance' => 'string',
        'last_synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(Token::class);
    }

    /** Get balance formatted using token decimals. */
    public function formattedBalance(): string
    {
        $token = $this->token;
        if (! $token) {
            return $this->balance;
        }

        return $token->formatAmount($this->balance);
    }
}
