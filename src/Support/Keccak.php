<?php

namespace Roberts\Web3Laravel\Support;

class Keccak
{
    /**
     * Compute Keccak-256. Returns 0x-hex. If $hexInput=true, input is hex string (with/without 0x).
     */
    public static function hash(string $input, bool $hexInput = false): string
    {
        $bin = $hexInput ? hex2bin(self::normalizeHex($input)) : $input;
        // Prefer kornrunner/keccak for Ethereum-compatible Keccak-256
        if (class_exists('kornrunner\\Keccak')) {
            $cls = 'kornrunner\\Keccak';
            // raw_output=false to return hex string
            $digest = $cls::hash($bin, 256, false);

            return '0x'.strtolower($digest);
        }
        // Fallback to ext/hash keccak if available
        if (in_array('keccak256', hash_algos(), true)) {
            return '0x'.strtolower(hash('keccak256', $bin, false));
        }
        throw new \RuntimeException('Keccak-256 not available. Require kornrunner/keccak or ext-hash keccak.');
    }

    private static function normalizeHex(string $hex): string
    {
        $hex = Hex::stripZero($hex);
        if (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        return $hex;
    }
}
