<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Roberts\Web3Laravel\Concerns\InteractsWithWeb3;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Enums\WalletType;

/**
 * @property int $id
 * @property string $address
 * @property string|null $key
 * @property WalletType $wallet_type
 * @property \Roberts\Web3Laravel\Enums\BlockchainProtocol $protocol
 * @property int|null $owner_id
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property array|null $meta
 * @property-read \Illuminate\Foundation\Auth\User|null $user
 */
class Wallet extends Model
{
    use HasFactory, InteractsWithWeb3;

    protected $table = 'wallets';

    protected $guarded = ['id'];

    protected $hidden = ['key']; // never expose the encrypted key in arrays/json

    protected $casts = [
        'wallet_type' => WalletType::class,
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'meta' => 'array',
        'protocol' => BlockchainProtocol::class,
    ];

    // Relationships
    public function user(): EloquentBelongsTo
    {
        $userModel = config('auth.providers.users.model');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        return $this->belongsTo($userModel, 'owner_id');
    }

    // Note: Wallet no longer belongs to a specific blockchain; it carries a protocol instead.

    /**
     * Get key releases for this wallet.
     */
    public function keyReleases(): HasMany
    {
        return $this->hasMany(KeyRelease::class);
    }

    /**
     * Get all NFTs owned by this wallet.
     */
    public function nfts(): HasMany
    {
        return $this->hasMany(WalletNft::class);
    }

    /**
     * Get all ERC-20 balances tracked for this wallet.
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(WalletToken::class);
    }

    /**
     * Convenience: get a specific tracked token balance (raw), or null.
     */
    public function tokenBalanceFor(Token $token): ?string
    {
        /** @var WalletToken|null $row */
        $row = $this->tokens()->where('token_id', $token->id)->first();

        return $row?->balance;
    }

    /**
     * Convenience: check ERC-20 allowance from this wallet (owner) to a spender.
     */
    public function allowance(Token $token, string $spender): string
    {
        $service = app(\Roberts\Web3Laravel\Services\TokenService::class);

        return $service->allowance($token, $this->address, $spender);
    }

    /**
     * Get NFTs from a specific collection.
     */
    public function nftsFromCollection(NftCollection $collection): HasMany
    {
        return $this->hasMany(WalletNft::class)
            ->where('nft_collection_id', $collection->id);
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
        if ($this->key && ! $this->canStorePrivateKey()) {
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
        if ($key && ! $this->canStorePrivateKey()) {
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

        if (! $user) {
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

        if (! $user) {
            return [
                'can_release' => false,
                'reasons' => ['User must be authenticated'],
                'wallet_type' => $this->wallet_type->value,
                'has_private_key' => ! is_null($this->key),
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

        if (! $user) {
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

    // NFT Helper Methods

    /**
     * Get the total number of NFTs owned by this wallet.
     */
    public function getNftCount(): int
    {
        return $this->nfts()->count();
    }

    /**
     * Get the number of unique NFT collections owned.
     */
    public function getUniqueCollectionCount(): int
    {
        return $this->nfts()
            ->distinct('nft_collection_id')
            ->count('nft_collection_id');
    }

    /**
     * Get NFTs by collection.
     */
    public function getNftsByCollection(): Collection
    {
        return $this->nfts()
            ->with('nftCollection')
            ->get()
            ->groupBy('nft_collection_id');
    }

    /**
     * Check if this wallet owns a specific NFT.
     */
    public function ownsNft(NftCollection $collection, string $tokenId): bool
    {
        return $this->nfts()
            ->where('nft_collection_id', $collection->id)
            ->where('token_id', $tokenId)
            ->exists();
    }

    /**
     * Get estimated portfolio value (placeholder for future implementation).
     */
    public function getEstimatedPortfolioValue(): ?string
    {
        // This would calculate the total value of all tokens and NFTs
        // Based on current market prices
        return null;
    }

    /**
     * Get NFT gallery for display purposes.
     */
    public function getNftGallery(int $limit = 20): Collection
    {
        return $this->nfts()
            ->with('nftCollection')
            ->orderBy('acquired_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
