<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Concerns\InteractsWithWeb3;
use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Models\KeyRelease;

/**
 * @property int $id
 * @property string $address
 * @property string|null $key
 * @property WalletType $wallet_type
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
        'wallet_type' => WalletType::class,
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

    /**
     * Get key releases for this wallet.
     */
    public function keyReleases(): HasMany
    {
        return $this->hasMany(KeyRelease::class);
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

    // Wallet Type Methods

    /**
     * Scope to filter wallets by type.
     */
    public function scopeOfType($query, WalletType $type)
    {
        return $query->where('wallet_type', $type);
    }

    /**
     * Check if this is a custodial wallet.
     */
    public function isCustodial(): bool
    {
        return $this->wallet_type === WalletType::CUSTODIAL;
    }

    /**
     * Check if this is a shared wallet.
     */
    public function isShared(): bool
    {
        return $this->wallet_type === WalletType::SHARED;
    }

    /**
     * Check if this is an external wallet.
     */
    public function isExternal(): bool
    {
        return $this->wallet_type === WalletType::EXTERNAL;
    }

    /**
     * Check if this wallet can store private keys.
     */
    public function canStorePrivateKey(): bool
    {
        return $this->wallet_type->canStorePrivateKey();
    }

    /**
     * Check if this wallet requires external signing.
     */
    public function requiresExternalSigning(): bool
    {
        return $this->wallet_type->requiresExternalSigning();
    }

    /**
     * Get the wallet type label.
     */
    public function getTypeLabel(): string
    {
        return $this->wallet_type->label();
    }

    /**
     * Get the wallet type description.
     */
    public function getTypeDescription(): string
    {
        return $this->wallet_type->description();
    }

    /**
     * Validate that private key is only set for appropriate wallet types.
     */
    public function validatePrivateKeyForType(): void
    {
        if ($this->key && !$this->canStorePrivateKey()) {
            throw new \InvalidArgumentException(
                "Private key cannot be stored for {$this->wallet_type->value} wallet type"
            );
        }
    }

    /**
     * Set the private key with type validation.
     */
    public function setPrivateKey(?string $key): void
    {
        if ($key && !$this->canStorePrivateKey()) {
            throw new \InvalidArgumentException(
                "Cannot set private key for {$this->wallet_type->value} wallet type"
            );
        }
        
        $this->key = $key;
    }

    // Key Release Methods

    /**
     * Securely release the private key to the wallet owner.
     */
    public function releasePrivateKey(?Model $user = null): array
    {
        $keyReleaseService = app(\Roberts\Web3Laravel\Services\KeyReleaseService::class);
        $user = $user ?? Auth::user();

        if (!$user) {
            throw new \InvalidArgumentException('User must be provided or authenticated');
        }

        return $keyReleaseService->releasePrivateKey($this, $user, request());
    }

    /**
     * Check if the private key can be released to a user.
     */
    public function canReleaseKey(?Model $user = null): array
    {
        $keyReleaseService = app(\Roberts\Web3Laravel\Services\KeyReleaseService::class);
        $user = $user ?? Auth::user();

        if (!$user) {
            return [
                'can_release' => false,
                'reasons' => ['User must be authenticated'],
                'wallet_type' => $this->wallet_type->value,
                'has_private_key' => !is_null($this->key),
            ];
        }

        return $keyReleaseService->canReleaseKey($this, $user);
    }

    /**
     * Get key release history for this wallet.
     */
    public function getReleaseHistory(?Model $user = null): array
    {
        $keyReleaseService = app(\Roberts\Web3Laravel\Services\KeyReleaseService::class);
        $user = $user ?? Auth::user();

        if (!$user) {
            throw new \InvalidArgumentException('User must be provided or authenticated');
        }

        return $keyReleaseService->getReleaseHistory($this, $user);
    }

    /**
     * Get the number of times the key has been released.
     */
    public function getKeyReleaseCount(): int
    {
        return $this->keyReleases()->count();
    }

    /**
     * Get the last time the key was released.
     */
    public function getLastKeyRelease(): ?\Illuminate\Support\Carbon
    {
        /** @var KeyRelease|null $lastRelease */
        $lastRelease = $this->keyReleases()
            ->latest('released_at')
            ->first();

        return $lastRelease?->released_at;
    }
}
