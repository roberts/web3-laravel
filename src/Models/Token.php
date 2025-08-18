<?php

namespace Roberts\Web3Laravel\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Roberts\Web3Laravel\Services\TokenService;

/**
 * Represents fungible tokens (ERC-20 only)
 *
 * @property int $id
 * @property int $contract_id
 * @property string $symbol
 * @property string $name
 * @property int $decimals
 * @property string $total_supply
 * @property string|null $icon_url
 * @property array|null $metadata
 * @property-read Contract $contract
 */
class Token extends Model
{
    use HasFactory;

    protected $table = 'tokens';

    protected $guarded = ['id'];

    protected $casts = [
        'contract_id' => 'integer',
        'decimals' => 'integer',
        'total_supply' => 'string',
        'metadata' => 'array',
        'price_usd' => 'decimal:8',
        'market_cap_usd' => 'decimal:2',
        'volume_24h_usd' => 'decimal:2',
        'percent_change_24h' => 'decimal:2',
        'price_updated_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get transactions related to this token.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'contract_id', 'contract_id');
    }

    /**
     * Get the balance for a specific wallet address.
     */
    public function getBalance(string $walletAddress): string
    {
        $service = app(TokenService::class);

        return $service->balanceOf($this, $walletAddress);
    }

    /**
     * Get the wallet balance for this token.
     */
    public function getWalletBalance(Wallet $wallet): string
    {
        return $this->getBalance($wallet->address);
    }

    /**
     * Get all wallets that hold this token.
     *
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function getHolders(): \Illuminate\Support\Collection
    {
        // This would typically query a wallet_tokens table or use blockchain data
        // Placeholder implementation
        return collect();
    }

    /**
     * Get total circulating supply.
     */
    public function getTotalCirculatingSupply(): string
    {
        return $this->total_supply;
    }

    /**
     * Get display name for the token.
     */
    public function getDisplayName(): string
    {
        return $this->name ?: $this->symbol;
    }

    /**
     * Get formatted supply with decimals.
     */
    public function getFormattedSupply(): string
    {
        return $this->formatAmount($this->total_supply);
    }

    /**
     * Get token icon URL.
     */
    public function getIconUrl(): ?string
    {
        return $this->metadata['icon_url'] ?? null;
    }

    /**
     * Get current price (placeholder for future market data integration).
     */
    public function getCurrentPrice(): ?string
    {
        return $this->metadata['price'] ?? null;
    }

    /**
     * Get market cap (placeholder for future market data integration).
     */
    public function getMarketCap(): ?string
    {
        if ($price = $this->getCurrentPrice()) {
            // Simple calculation: price * total_supply
            return bcmul($price, $this->total_supply);
        }

        return null;
    }

    /**
     * Format amount according to token decimals.
     */
    public function formatAmount(string $rawAmount): string
    {
        $decimals = $this->decimals;
        $length = strlen($rawAmount);

        if ($length <= $decimals) {
            $padded = str_pad($rawAmount, $decimals, '0', STR_PAD_LEFT);
            $trimmed = rtrim($padded, '0');

            return $trimmed ? '0.'.$trimmed : '0';
        }

        $wholePart = substr($rawAmount, 0, $length - $decimals);
        $decimalPart = rtrim(substr($rawAmount, $length - $decimals), '0');

        return $decimalPart ? $wholePart.'.'.$decimalPart : $wholePart;
    }

    /**
     * Parse formatted amount to raw amount.
     */
    public function parseAmount(string $formattedAmount): string
    {
        if (! str_contains($formattedAmount, '.')) {
            return bcmul($formattedAmount, bcpow('10', (string) $this->decimals));
        }

        [$whole, $decimal] = explode('.', $formattedAmount);
        $decimal = str_pad($decimal, $this->decimals, '0', STR_PAD_RIGHT);
        $decimal = substr($decimal, 0, $this->decimals); // Ensure we don't exceed decimals

        // Handle the case where whole part is "0"
        if ($whole === '0') {
            return $decimal;
        }

        return $whole.$decimal;
    }

    /**
     * Check if this token has sufficient metadata.
     */
    public function hasCompleteMetadata(): bool
    {
        return ! empty($this->name) && ! empty($this->symbol) && isset($this->decimals);
    }

    /**
     * Check if this token is ERC-20 (always true for Token model)
     */
    public function isERC20(): bool
    {
        return true;
    }

    /**
     * Check if this token is ERC-721 (always false for Token model)
     */
    public function isERC721(): bool
    {
        return false;
    }

    /**
     * Check if this token is ERC-1155 (always false for Token model)
     */
    public function isERC1155(): bool
    {
        return false;
    }

    /**
     * Get the token type (always ERC-20 for Token model)
     */
    public function getTokenType(): string
    {
        return 'erc20';
    }

    /**
     * Virtual accessor for backward compatibility
     */
    public function getTokenTypeAttribute(): string
    {
        return 'erc20';
    }
}
