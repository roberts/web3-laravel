<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Roberts\Web3Laravel\Enums\TokenType;

class Token extends Model
{
    use HasFactory;

    protected $table = 'tokens';

    protected $guarded = ['id'];

    protected $casts = [
        'contract_id' => 'integer',
        'quantity' => 'string',
        'token_type' => TokenType::class,
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
