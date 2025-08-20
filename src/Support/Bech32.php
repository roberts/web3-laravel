<?php

namespace Roberts\Web3Laravel\Support;

/**
 * Minimal Bech32 encoder with SegWit helper (version 0 P2WPKH/P2WSH).
 * Source adapted from BIP-0173 reference with small adjustments for PHP.
 */
class Bech32
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    private const GEN = [
        0x3B6A57B2,
        0x26508E6D,
        0x1EA119FA,
        0x3D4233DD,
        0x2A1462B3,
    ];

    private static function polymod(array $values): int
    {
        $chk = 1;
        foreach ($values as $v) {
            $b = $chk >> 25;
            $chk = (($chk & 0x1FFFFFF) << 5) ^ $v;
            for ($i = 0; $i < 5; $i++) {
                $chk ^= (($b >> $i) & 1) ? self::GEN[$i] : 0;
            }
        }

        return $chk;
    }

    private static function hrpExpand(string $hrp): array
    {
        $v = [];
        $len = strlen($hrp);
        for ($i = 0; $i < $len; $i++) {
            $v[] = ord($hrp[$i]) >> 5;
        }
        $v[] = 0;
        for ($i = 0; $i < $len; $i++) {
            $v[] = ord($hrp[$i]) & 31;
        }

        return $v;
    }

    private static function createChecksum(string $hrp, array $data): array
    {
        $values = array_merge(self::hrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $mod = self::polymod($values) ^ 1;
        $ret = [];
        for ($p = 0; $p < 6; $p++) {
            $ret[] = ($mod >> (5 * (5 - $p))) & 31;
        }

        return $ret;
    }

    private static function convertBits(string $data, int $from, int $to, bool $pad = true): array
    {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $to) - 1;
        $maxacc = (1 << ($from + $to - 1)) - 1;

        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $value = ord($data[$i]);
            if (($value >> $from)) {
                return [];
            }
            $acc = (($acc << $from) | $value) & $maxacc;
            $bits += $from;
            while ($bits >= $to) {
                $bits -= $to;
                $ret[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << ($to - $bits)) & $maxv;
            }
        } elseif ($bits >= $from || (($acc << ($to - $bits)) & $maxv)) {
            return [];
        }

        return $ret;
    }

    public static function encode(string $hrp, array $data): string
    {
        $combined = array_merge($data, self::createChecksum($hrp, $data));
        $chars = '';
        foreach ($combined as $d) {
            $chars .= self::CHARSET[$d];
        }

        return strtolower($hrp.'1'.$chars);
    }

    /**
     * Encode a SegWit address for version 0 using witness program bytes (20 or 32 bytes).
     */
    public static function encodeSegwit(string $hrp, int $version, string $program): string
    {
        if ($version < 0 || $version > 16) {
            throw new \InvalidArgumentException('Invalid segwit version');
        }
        $data = [$version];
        $prog5 = self::convertBits($program, 8, 5, true);
        if (empty($prog5)) {
            throw new \InvalidArgumentException('Invalid witness program');
        }
        $data = array_merge($data, $prog5);

        return self::encode($hrp, $data);
    }
}
