<?php

namespace Roberts\Web3Laravel\Support;

class Hex
{
    public static function isZeroPrefixed(string $hex): bool
    {
        return str_starts_with($hex, '0x') || str_starts_with($hex, '0X');
    }

    public static function ensure0x(string $hex): string
    {
        return self::isZeroPrefixed($hex) ? $hex : ('0x'.ltrim($hex, 'x'));
    }

    public static function stripZero(string $hex): string
    {
        return self::isZeroPrefixed($hex) ? substr($hex, 2) : $hex;
    }

    /**
     * Convert a decimal integer or decimal string to 0x-hex. When $quantity=true, no leading zeros.
     * Accepts hex strings and returns normalized 0x form.
     */
    public static function toHex(int|string $value, bool $quantity = false): string
    {
        if (is_string($value) && preg_match('/^0x[0-9a-fA-F]+$/', $value)) {
            $hex = strtolower($value);
            // If quantity requested, normalize to no leading zeros
            if ($quantity) {
                $s = ltrim(substr($hex, 2), '0');
                if ($s === '') { $s = '0'; }
                return '0x'.$s;
            }
            return $hex;
        }

        // Use GMP for big integers if available
        if (function_exists('gmp_init')) {
            $g = gmp_init((string) $value, 10);
            $hex = gmp_strval($g, 16);
        } else {
            $hex = dechex((int) $value);
        }
        if (! $quantity) {
            // pad to even length
            if (strlen($hex) % 2 !== 0) {
                $hex = '0'.$hex;
            }
        } else {
            $hex = ltrim($hex, '0');
            if ($hex === '') { $hex = '0'; }
        }

        return '0x'.strtolower($hex);
    }
}
