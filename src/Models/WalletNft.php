<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $nft_collection_id
 * @property string $token_id
 * @property string $quantity
 * @property string|null $metadata_uri
 * @property array|null $metadata
 * @property array|null $traits
 * @property int|null $rarity_rank
 * @property Carbon|null $acquired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 * @property-read NftCollection $nftCollection
 */
class WalletNft extends Model
{
    use HasFactory;

    protected $table = 'wallet_nfts';

    protected $guarded = ['id'];

    protected $casts = [
        'wallet_id' => 'integer',
        'nft_collection_id' => 'integer',
        'quantity' => 'string',
        'metadata' => 'array',
        'traits' => 'array',
        'rarity_rank' => 'integer',
        'acquired_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function nftCollection(): BelongsTo
    {
        return $this->belongsTo(NftCollection::class);
    }

    /**
     * Get metadata, optionally refreshing from the blockchain.
     */
    public function getMetadata(bool $refresh = false): ?array
    {
        if ($refresh || ! $this->metadata) {
            // In a real implementation, this would fetch from IPFS/API
            // For now, return cached metadata
        }

        return $this->metadata;
    }

    /**
     * Get the image URL from metadata.
     */
    public function getImageUrl(): ?string
    {
        $metadata = $this->getMetadata();

        return $metadata['image'] ?? $metadata['image_url'] ?? null;
    }

    /**
     * Get the name from metadata.
     */
    public function getName(): ?string
    {
        $metadata = $this->getMetadata();

        return $metadata['name'] ?? "#{$this->token_id}";
    }

    /**
     * Get the description from metadata.
     */
    public function getDescription(): ?string
    {
        $metadata = $this->getMetadata();

        return $metadata['description'] ?? null;
    }

    /**
     * Get formatted traits array.
     */
    public function getTraits(): array
    {
        return $this->traits ?? [];
    }

    /**
     * Get the rarity rank if available.
     */
    public function getRarityRank(): ?int
    {
        return $this->rarity_rank;
    }

    /**
     * Get a calculated rarity score based on traits.
     */
    public function getRarityScore(): ?float
    {
        // Placeholder for rarity calculation
        // In a real implementation, this would calculate based on trait rarity
        return null;
    }

    /**
     * Get estimated value (placeholder for future market data integration).
     */
    public function getEstimatedValue(): ?string
    {
        // Placeholder for market value estimation
        return null;
    }

    /**
     * Get transfer history for this NFT.
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getTransferHistory(): \Illuminate\Support\Collection
    {
        // Placeholder - would query transaction table for transfers
        return collect();
    }

    /**
     * Get the last transfer transaction.
     */
    public function getLastTransfer(): ?Transaction
    {
        // Placeholder - would get the most recent transfer
        return null;
    }

    /**
     * Check if this is a semi-fungible token (ERC-1155 with quantity > 1).
     */
    public function isSemiFungible(): bool
    {
        return $this->nftCollection->standard->isSemiFungible() &&
               bccomp($this->quantity, '1') > 0;
    }

    /**
     * Check if we can transfer a specific quantity.
     */
    public function canTransferQuantity(string $amount): bool
    {
        if (! $this->nftCollection->standard->supportsQuantity()) {
            return $amount === '1' && $this->quantity === '1';
        }

        return bccomp($amount, $this->quantity) <= 0;
    }

    /**
     * Get a display name for this NFT.
     */
    public function getDisplayName(): string
    {
        $name = $this->getName();
        $collectionName = $this->nftCollection->name;

        return $name ?: "{$collectionName} #{$this->token_id}";
    }

    /**
     * Check if metadata needs refreshing (older than 24 hours).
     */
    public function needsMetadataRefresh(): bool
    {
        return ! $this->metadata || $this->updated_at->diffInHours(now()) > 24;
    }
}
