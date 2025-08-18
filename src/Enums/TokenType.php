<?php

namespace Roberts\Web3Laravel\Enums;

enum TokenType: string
{
    case ERC20 = 'erc20';
    case ERC721 = 'erc721';
    case ERC1155 = 'erc1155';

    /**
     * Check if this token type supports semi-fungible behavior
     */
    public function isSemiFungible(): bool
    {
        return $this === self::ERC1155;
    }

    /**
     * Check if this token type supports quantity tracking
     */
    public function supportsQuantity(): bool
    {
        return $this === self::ERC1155;
    }

    /**
     * Get display name for the token standard
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::ERC20 => 'ERC-20 (Fungible Token)',
            self::ERC721 => 'ERC-721 (Non-Fungible Token)',
            self::ERC1155 => 'ERC-1155 (Multi-Token)',
        };
    }

    /**
     * Check if this is an NFT standard
     */
    public function isNft(): bool
    {
        return $this === self::ERC721 || $this === self::ERC1155;
    }

    /**
     * Check if this is a fungible token standard
     */
    public function isFungible(): bool
    {
        return $this === self::ERC20;
    }
}
