<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Support\Address;

/**
 * @property int $id
 * @property int|null $blockchain_id
 * @property string|null $address
 * @property string|null $creator
 * @property array|null $abi
 * @property-read Blockchain $blockchain
 */
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
        if (! $value) {
            $this->attributes['address'] = null;

            return;
        }

        // Only normalize if EVM-like hex; otherwise store as-is (e.g., Solana base58)
        $isHex = (bool) preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $value);
        $this->attributes['address'] = $isHex ? Address::normalize($value) : $value;
    }

    public function setCreatorAttribute(?string $value): void
    {
        $this->attributes['creator'] = $value ? Address::normalize($value) : null;
    }

    /** Present addresses in EIP-55 checksum form when accessed. */
    public function getAddressAttribute(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

    // If blockchain protocol is EVM or it looks like hex, return normalized lowercase; else return raw
        try {
            $protocol = ($this->blockchain instanceof \Roberts\Web3Laravel\Models\Blockchain)
                ? $this->blockchain->protocol
                : null;
            $isHex = (bool) preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $value);
            if (($protocol && $protocol->isEvm()) || $isHex) {
        return Address::normalize($value);
            }

            return $value;
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function getCreatorAttribute(?string $value): ?string
    {
        return $value ? Address::normalize($value) : null;
    }

    // Eloquent-style read-only call shortcut
    public function call(string $function, array $params = [], ?string $from = null, string $blockTag = 'latest'): array
    {
        /** @var ContractCaller $svc */
        $svc = app(ContractCaller::class);

        return $svc->call($this->abi ?? [], (string) $this->address, $function, $params, $from, $blockTag);
    }

    // Convenience wrapper for simple single-value returns
    public function callValue(string $function, array $params = [], ?string $from = null, string $blockTag = 'latest')
    {
        $res = $this->call($function, $params, $from, $blockTag);

        return $res[0] ?? null;
    }

    // Magic accessor: $contract->callSymbol(), $contract->callName(), etc.
    public function __call($method, $parameters)
    {
        if (str_starts_with($method, 'call') && strlen($method) > 4) {
            $fn = lcfirst(substr($method, 4));
            $args = $parameters[0] ?? [];
            $from = $parameters[1] ?? null;
            $blockTag = $parameters[2] ?? 'latest';

            return $this->call($fn, $args, $from, $blockTag);
        }

        return parent::__call($method, $parameters);
    }
}
