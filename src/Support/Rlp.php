<?php

namespace Roberts\Web3Laravel\Support;

use Web3\Utils as Web3Utils;

class Rlp
{
    /** Encode a string (binary) per RLP. */
    public static function encodeString(string $input): string
    {
        $len = strlen($input);
        if ($len === 1 && ord($input) < 0x80) {
            return $input;
        }
        if ($len <= 55) {
            return chr(0x80 + $len).$input;
        }
        $lenBytes = self::toBinary($len);

        return chr(0xB7 + strlen($lenBytes)).$lenBytes.$input;
    }

    /** Encode a list of binary strings per RLP. */
    public static function encodeList(array $items): string
    {
        $payload = '';
        foreach ($items as $item) {
            $payload .= $item;
        }
        $len = strlen($payload);
        if ($len <= 55) {
            return chr(0xC0 + $len).$payload;
        }
        $lenBytes = self::toBinary($len);

        return chr(0xF7 + strlen($lenBytes)).$lenBytes.$payload;
    }

    /** Convert integer to big-endian binary (no leading zeros). */
    public static function toBinary(int $value): string
    {
        if ($value === 0) {
            return "\x00";
        }
        $bin = '';
        while ($value > 0) {
            $bin = chr($value & 0xFF).$bin;
            $value >>= 8;
        }

        return $bin;
    }

    /** Encode integer to RLP (no leading zeros). */
    public static function encodeInt(int $value): string
    {
        if ($value === 0) {
            return chr(0x80);
        }

        return self::encodeString(self::toBinary($value));
    }

    /** Encode a hex string (0x...) to RLP string. */
    public static function encodeHex(string $hex): string
    {
        // $hex is already typed as string; check kept for runtime safety
        $hex = Web3Utils::stripZero($hex);
        if ($hex === '') {
            return chr(0x80); // empty
        }
        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }
        $bin = pack('H*', $hex);

        return self::encodeString($bin);
    }
}
