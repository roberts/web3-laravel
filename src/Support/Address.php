<?php

namespace Roberts\Web3Laravel\Support;

use Roberts\Web3Laravel\Enums\BlockchainProtocol;

class Address
{
    /** Normalize an address to lowercase 0x-prefixed hex (no checksum). */
    public static function normalize(string $address): string
    {
        $addr = strtolower($address);

        return str_starts_with($addr, '0x') ? $addr : ('0x'.$addr);
    }

    /**
     * Validate an EVM address. If $strictChecksum=true and address is mixed-case, enforce EIP-55 checksum.
     */
    public static function isValidEvm(string $address, bool $strictChecksum = false): bool
    {
        if (! preg_match('/^(0x)?[0-9a-fA-F]{40}$/', $address)) {
            return false;
        }

        // If all lower or all upper, accept without checksum (unless strict)
        $hex = str_starts_with($address, '0x') ? substr($address, 2) : $address;
        if (! $strictChecksum && (strtolower($hex) === $hex || strtoupper($hex) === $hex)) {
            return true;
        }

        return self::toChecksum($address) === self::ensure0x($address);
    }

    /** Return EIP-55 checksummed address (0x-prefixed). */
    public static function toChecksum(string $address): string
    {
        $hex = self::strip0x($address);
        $lower = strtolower($hex);
        $hash = Keccak::hash($lower, false); // 0x-hex of keccak(lowercase-hex-ascii)
        $hashHex = Hex::stripZero($hash);
        $result = '';
        for ($i = 0; $i < 40; $i++) {
            $char = $lower[$i];
            if (ctype_alpha($char)) {
                $hashNibble = hexdec($hashHex[$i]);
                $result .= ($hashNibble >= 8) ? strtoupper($char) : $char;
            } else {
                $result .= $char;
            }
        }

        return '0x'.$result;
    }

    /** Case-insensitive equality for addresses (by normalized lowercase). */
    public static function equals(string $a, string $b): bool
    {
        return strtolower(self::ensure0x($a)) === strtolower(self::ensure0x($b));
    }

    /** Utility: ensure 0x prefix. */
    public static function ensure0x(string $address): string
    {
        return str_starts_with($address, '0x') || str_starts_with($address, '0X')
            ? $address
            : '0x'.$address;
    }

    /** Utility: strip 0x prefix. */
    public static function strip0x(string $address): string
    {
        return str_starts_with($address, '0x') || str_starts_with($address, '0X')
            ? substr($address, 2)
            : $address;
    }

    /** Quick protocol-aware validity (currently EVM only). */
    public static function isValidForProtocol(string $address, BlockchainProtocol $protocol): bool
    {
        if ($protocol->isEvm()) {
            return self::isValidEvm($address);
        }

        // For non-EVM protocols, defer to other validators in future
        return $address !== '';
    }
}
