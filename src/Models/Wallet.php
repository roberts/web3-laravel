<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Concerns\InteractsWithWeb3;

/**
 * @property int $id
 * @property string $address
 * @property string|null $key
 * @property int|null $blockchain_id
 * @property int|null $owner_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property array|null $meta
 * @property-read Blockchain|null $blockchain
 * @property-read \Illuminate\Foundation\Auth\User|null $user
 */
class Wallet extends Model
{
    use HasFactory, InteractsWithWeb3;

    protected $table = 'wallets';

    protected $guarded = ['id'];

    protected $hidden = ['key']; // never expose the encrypted key in arrays/json

    protected $casts = [
        'blockchain_id' => 'integer',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships
    public function user(): EloquentBelongsTo
    {
        $userModel = config('auth.providers.users.model');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        return $this->belongsTo($userModel, 'owner_id');
    }

    public function blockchain(): BelongsTo
    {
        return $this->belongsTo(Blockchain::class);
    }

    // Attribute mutator: always encrypt when setting
    public function setKeyAttribute(?string $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['key'] = null;

            return;
        }

        // Avoid double-encrypting if someone passes already-encrypted text from our own store
        try {
            // If it decrypts cleanly, assume already encrypted and keep as-is
            Crypt::decryptString($value);
            $this->attributes['key'] = $value;
        } catch (DecryptException) {
            $this->attributes['key'] = Crypt::encryptString($value);
        }
    }

    /**
     * Explicit helper to get the decrypted private key.
     * Intentionally not exposed as an accessor to avoid accidental serialization.
     */
    public function decryptKey(): ?string
    {
        $encrypted = $this->attributes['key'] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    /** Masked preview of key for logs/debug (never full key). */
    public function maskedKey(): ?string
    {
        $plain = $this->decryptKey();
        if ($plain === null) {
            return null;
        }
        $len = strlen($plain);
        $start = substr($plain, 0, 6);
        $end = substr($plain, max(0, $len - 4));

        return $start.str_repeat('*', max(0, $len - 10)).$end;
    }

    // Scopes
    public function scopeByAddress($query, string $address)
    {
        return $query->where('address', strtolower($address));
    }

    public function scopeForUser($query, Model $user)
    {
        return $query->where('owner_id', $user->getKey());
    }
}
