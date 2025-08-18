<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Roberts\Web3Laravel\Enums\TokenType;

/**
 * @property int $id
 * @property int $contract_id
 * @property string $name
 * @property string $symbol
 * @property string|null $description
 * @property string|null $image_url
 * @property string|null $banner_url
 * @property string|null $external_url
 * @property TokenType $standard
 * @property string|null $total_supply
 * @property string|null $floor_price
 * @property array|null $metadata
 * @property-read Contract $contract
 * @property-read Collection<WalletNft> $walletNfts
 * @property-read Collection<Wallet> $owners
 */
class NftCollection extends Model
{
    use HasFactory;

    protected $table = 'nft_collections';

    protected $guarded = ['id'];

    protected $casts = [
        'contract_id' => 'integer',
        'standard' => TokenType::class,
        'total_supply' => 'string',
        'floor_price' => 'string',
        'metadata' => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function walletNfts(): HasMany
    {
        return $this->hasMany(WalletNft::class);
    }

    public function owners(): HasManyThrough
    {
        return $this->hasManyThrough(Wallet::class, WalletNft::class, 'nft_collection_id', 'id', 'id', 'wallet_id');
    }

    /**
     * Get the number of unique owners of this collection.
     */
    public function getOwnerCount(): int
    {
        return $this->walletNfts()
            ->distinct('wallet_id')
            ->count('wallet_id');
    }

    /**
     * Get the number of unique tokens in this collection.
     */
    public function getUniqueTokenCount(): int
    {
        return $this->walletNfts()
            ->distinct('token_id')
            ->count('token_id');
    }

    /**
     * Get the floor price as a formatted string.
     */
    public function getFloorPrice(): ?string
    {
        return $this->floor_price;
    }

    /**
     * Get the collection image URL or a fallback.
     */
    public function getCollectionImage(): ?string
    {
        return $this->image_url;
    }

    /**
     * Get the external link for this collection.
     */
    public function getExternalLink(): ?string
    {
        return $this->external_url;
    }

    /**
     * Check if this collection is verified (placeholder for future verification system).
     */
    public function isVerified(): bool
    {
        return $this->metadata['verified'] ?? false;
    }

    /**
     * Get all tokens owned by a specific wallet.
     */
    public function getTokensOwnedBy(Wallet $wallet): Collection
    {
        return $this->walletNfts()
            ->where('wallet_id', $wallet->id)
            ->get();
    }

    /**
     * Get a specific token by ID.
     */
    public function getTokenById(string $tokenId): ?WalletNft
    {
        /** @var WalletNft|null $result */
        $result = $this->walletNfts()
            ->where('token_id', $tokenId)
            ->first();

        return $result;
    }

    /**
     * Get tokens ordered by rarity ranking.
     */
    public function getRarityRanking(): Collection
    {
        return $this->walletNfts()
            ->whereNotNull('rarity_rank')
            ->orderBy('rarity_rank')
            ->get();
    }

    /**
     * Get the distribution of owners (how many tokens each owner has).
     */
    public function getOwnerDistribution(): array
    {
        return $this->walletNfts()
            ->selectRaw('wallet_id, COUNT(*) as token_count')
            ->groupBy('wallet_id')
            ->orderByDesc('token_count')
            ->pluck('token_count', 'wallet_id')
            ->toArray();
    }

    /**
     * Get the distribution of traits across all tokens.
     */
    public function getTraitDistribution(): array
    {
        $allTraits = $this->walletNfts()
            ->whereNotNull('traits')
            ->pluck('traits')
            ->flatten(1);

        $distribution = [];
        foreach ($allTraits as $traits) {
            if (is_array($traits)) {
                foreach ($traits as $trait) {
                    if (isset($trait['trait_type']) && isset($trait['value'])) {
                        $key = $trait['trait_type'];
                        $value = $trait['value'];
                        $distribution[$key][$value] = ($distribution[$key][$value] ?? 0) + 1;
                    }
                }
            }
        }

        return $distribution;
    }

    /**
     * Check if this collection supports semi-fungible tokens.
     */
    public function supportsSemiFungible(): bool
    {
        return $this->standard->isSemiFungible();
    }

    /**
     * Get the total number of tokens minted (considering quantities for ERC-1155).
     */
    public function getTotalMinted(): string
    {
        if ($this->standard->supportsQuantity()) {
            return $this->walletNfts()->sum('quantity');
        }

        return (string) $this->walletNfts()->count();
    }
}
