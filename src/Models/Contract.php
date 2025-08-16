<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $table = 'contracts';

    protected $guarded = ['id'];

    protected $casts = [
        'blockchain_id' => 'integer',
        'abi' => 'array',
    ];

    public function blockchain(): BelongsTo
    {
        return $this->belongsTo(Blockchain::class);
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['address'] = $value ? strtolower($value) : null;
    }

    public function setCreatorAttribute(?string $value): void
    {
        $this->attributes['creator'] = $value ? strtolower($value) : null;
    }
}
