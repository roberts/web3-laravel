<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Roberts\Web3Laravel\Services\ContractCaller;

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

    // Eloquent-style read-only call shortcut
    public function call(string $function, array $params = [], ?string $from = null, string $blockTag = 'latest'): array
    {
        /** @var ContractCaller $svc */
        $svc = app(ContractCaller::class);

        return $svc->call($this->abi ?? [], (string) $this->address, $function, $params, $from, $blockTag);
    }
}
