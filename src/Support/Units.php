<?php

namespace Roberts\Web3Laravel\Support;

class Units
{
    private const MAP = [
        'wei' => 0,
        'kwei' => 3, 'babbage' => 3,
        'mwei' => 6, 'lovelace' => 6,
        'gwei' => 9, 'shannon' => 9,
        'szabo' => 12,
        'finney' => 15,
        'ether' => 18,
    ];

    public static function toWei(string|int $amount, string $unit = 'ether'): string
    {
        $exp = self::MAP[strtolower($unit)] ?? 18;
        if (is_int($amount)) {
            if (function_exists('gmp_init')) {
                $i = gmp_init((string) $amount, 10);
                $mul = gmp_mul($i, gmp_pow(10, $exp));

                return '0x'.gmp_strval($mul, 16);
            }

            // best-effort for small ints
            return Hex::toHex((int) ($amount * (10 ** $exp)), true);
        }
        // decimal string multiply
        if (! function_exists('gmp_init')) {
            // naive fallback: drop decimals and cast safely
            [$int] = array_pad(explode('.', $amount, 2), 2, '0');
            $intOnly = preg_replace('/\D/', '', $int) ?? '0';
            $multiplier = (int) (10 ** $exp);
            $val = (int) $intOnly;

            return Hex::toHex($val * $multiplier, true);
        }
        $parts = explode('.', $amount, 2);
        $intPart = gmp_init($parts[0] === '' ? '0' : $parts[0], 10);
        $fracPart = $parts[1] ?? '0';
        $fracPart = substr($fracPart.'000000000000000000', 0, $exp);
        $i = gmp_mul($intPart, gmp_pow(10, $exp));
        $f = gmp_init($fracPart, 10);

        return '0x'.gmp_strval(gmp_add($i, $f), 16);
    }

    public static function fromWei(string $hexWei, string $unit = 'ether'): string
    {
        $exp = self::MAP[strtolower($unit)] ?? 18;
        $hex = Hex::stripZero($hexWei);
        if ($hex === '') {
            return '0';
        }
        if (! function_exists('gmp_init')) {
            $val = hexdec($hex);

            return (string) ($val / (10 ** $exp));
        }
        $v = gmp_init($hex, 16);
        $base = gmp_pow(10, $exp);
        $int = gmp_div_q($v, $base);
        $rem = gmp_div_r($v, $base);
        $frac = str_pad(gmp_strval($rem, 10), $exp, '0', STR_PAD_LEFT);
        $frac = rtrim($frac, '0');

        return $frac === '' ? gmp_strval($int, 10) : (gmp_strval($int, 10).'.'.$frac);
    }
}
