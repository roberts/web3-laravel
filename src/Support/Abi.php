<?php

namespace Roberts\Web3Laravel\Support;

/** Minimal ABI encode/decode supporting common static/dynamic types and tuples. */
class Abi
{
    /** @param array<int,string> $types */
    public static function encodeParameters(array $types, array $values): string
    {
        [$head, $tail] = self::encodeTuple($types, $values);
        return '0x'.bin2hex($head.$tail);
    }

    /** @param array<int,string> $types */
    public static function decodeParameters(array $types, string $data): array
    {
        $bin = hex2bin(Hex::stripZero($data)) ?: '';
        return self::decodeTuple($types, $bin, 0)[0];
    }

    /** Build function selector + encoded args from ABI + params. */
    public static function encodeFunctionCall(array $abi, string $name, array $params = []): string
    {
        $method = self::findFunction($abi, $name);
        $inputTypes = self::normalizeParamTypes($method['inputs'] ?? []);
        $signature = ($method['name'] ?? $name).'('.implode(',', $inputTypes).')';
        $selector = substr(Keccak::hash($signature), 2, 8);
        $encoded = self::encodeParameters($inputTypes, $params);
        return '0x'.$selector.substr($encoded, 2);
    }

    // --- Internals ---
    private static function findFunction(array $abi, string $name): array
    {
        foreach ($abi as $item) {
            if (($item['type'] ?? null) === 'function' && ($item['name'] ?? '') === $name) {
                return $item;
            }
        }
        throw new \InvalidArgumentException("Function {$name} not found in ABI.");
    }

    private static function normalizeParamTypes(array $params): array
    {
        $types = [];
        foreach ($params as $p) {
            $types[] = self::normalizeType($p);
        }
        return $types;
    }

    private static function normalizeType(array $param): string
    {
        $type = (string) ($param['type'] ?? 'bytes');
        if (str_starts_with($type, 'tuple')) {
            $suffix = substr($type, 5); // '', '[]', '[2]', ...
            $inner = [];
            foreach ((array) ($param['components'] ?? []) as $c) {
                $inner[] = self::normalizeType($c);
            }
            return '('.implode(',', $inner).')'.$suffix;
        }
        return $type;
    }

    private static function isDynamic(string $type): bool
    {
        if ($type === 'string' || $type === 'bytes') return true;
        if (preg_match('/^bytes(\d+)$/', $type)) return false;
        if (str_ends_with($type, '[]')) return true;
        if (preg_match('/\[[0-9]+\]$/', $type)) return false; // static-sized array
        if (str_starts_with($type, '(')) {
            // Bare tuple is static; only dynamic if it is a tuple array (handled by [] case above)
            return false;
        }
        return false;
    }

    private static function pad32(string $bin): string
    {
        $len = strlen($bin);
        $pad = (32 - ($len % 32)) % 32;
        return $bin.str_repeat("\x00", $pad);
    }

    private static function encUint($v): string
    {
        // $v may be string/int; convert using GMP if available
        if (function_exists('gmp_init')) {
            $g = gmp_init((string) $v, 10);
            $hex = gmp_strval($g, 16);
        } else {
            $hex = dechex((int) $v);
        }
        if (strlen($hex) % 2 !== 0) $hex = '0'.$hex;
        $bin = hex2bin($hex) ?: '';
        return str_repeat("\x00", 32 - strlen($bin)).$bin;
    }

    private static function encInt($v): string
    {
        // Minimal: treat like uint for non-negative; negative not currently required in this package
        return self::encUint($v);
    }

    private static function encAddress(string $hex): string
    {
        $hex = Hex::stripZero($hex);
        $hex = str_pad(strtolower($hex), 40, '0', STR_PAD_LEFT);
        $bin = hex2bin($hex) ?: '';
        return str_repeat("\x00", 12).$bin; // 12 + 20 = 32
    }

    private static function encBool($v): string
    {
        return self::encUint($v ? 1 : 0);
    }

    private static function encBytesFixed(string $hex, int $n): string
    {
        $hex = Hex::stripZero($hex);
        if (strlen($hex) > $n * 2) {
            $hex = substr($hex, 0, $n * 2);
        }
        $bin = hex2bin(str_pad($hex, $n * 2, '0', STR_PAD_RIGHT)) ?: '';
        return self::pad32($bin);
    }

    private static function encBytesDynamic(string $hexOrBin, bool $isHex = true): string
    {
        $bin = $isHex ? (hex2bin(Hex::stripZero($hexOrBin)) ?: '') : $hexOrBin;
        $len = strlen($bin);
        return self::encUint($len).self::pad32($bin);
    }

    private static function encodeOne(string $type, $value): array
    {
        // returns [head(32)|"", tail(0|n)] where head holds value or offset
        if ($type === 'string') return ['', self::encBytesDynamic($value, false)];
        if ($type === 'bytes') return ['', self::encBytesDynamic($value, true)];
        if (preg_match('/^bytes(\d+)$/', $type, $m)) return [self::encBytesFixed($value, (int) $m[1]), ''];
        if ($type === 'bool') return [self::encBool($value), ''];
        if ($type === 'address') return [self::encAddress($value), ''];
        if (preg_match('/^uint(\d+)?$/', $type)) return [self::encUint($value), ''];
        if (preg_match('/^int(\d+)?$/', $type)) return [self::encInt($value), ''];

        if (str_starts_with($type, '(')) {
            // tuple or tuple array
            if (str_ends_with($type, '[]')) {
                $inner = substr($type, 1, -3);
                $types = self::splitTupleTypes($inner);
                $elements = (array) $value;
                $packed = '';
                $packed .= self::encUint(count($elements));
                foreach ($elements as $el) {
                    [$h, $t] = self::encodeTuple($types, (array) $el);
                    $packed .= $h.$t;
                }
                return ['', $packed];
            }
            // bare tuple is static inline in the head
            $inner = rtrim(substr($type, 1), ')');
            $types = self::splitTupleTypes($inner);
            [$h, $t] = self::encodeTuple($types, (array) $value);
            return [$h.$t, ''];
        }

        if (str_ends_with($type, '[]')) {
            $elemType = substr($type, 0, -2);
            $values = (array) $value;
            $packed = self::encUint(count($values));
            $heads = [];
            $tails = [];
            foreach ($values as $v) {
                [$h, $t] = self::encodeOne($elemType, $v);
                $heads[] = $h; $tails[] = $t;
            }
            if (array_reduce($tails, fn($c,$x)=>$c || $x!=='' , false)) {
                // dynamic elements: need offsets
                $offset = count($values) * 32;
                $outHead = '';
                $outTail = '';
                foreach ($values as $i => $_) {
                    $outHead .= self::encUint($offset);
                    $outTail .= $heads[$i].$tails[$i];
                    $offset += strlen($heads[$i].$tails[$i]);
                }
                $packed .= $outHead.$outTail;
            } else {
                $packed .= implode('', $heads);
            }
            return ['', $packed];
        }

        if (preg_match('/\[(\d+)\]$/', $type, $m)) {
            $elemType = substr($type, 0, -(strlen($m[0])));
            $len = (int) $m[1];
            $values = (array) $value;
            $heads = '';
            $tails = '';
            foreach ($values as $v) {
                [$h, $t] = self::encodeOne($elemType, $v);
                $heads .= $h; $tails .= $t;
            }
            if ($tails !== '') {
                // static array with dynamic elems requires offsets into the tail
                $offset = $len * 32;
                $outHead = '';
                $outTail = '';
                for ($i=0; $i<$len; $i++) {
                    $chunk = substr($heads, $i*32, 32);
                    $dyn = substr($tails, $i*32) ?: '';
                    $outHead .= self::encUint($offset);
                    $outTail .= $chunk.$dyn;
                    $offset += strlen($chunk.$dyn);
                }
                return [$outHead, $outTail];
            }
            return [$heads, ''];
        }

        throw new \InvalidArgumentException('Unsupported type: '.$type);
    }

    /** @param array<int,string> $types */
    private static function encodeTuple(array $types, array $values): array
    {
        $heads = [];
        $tails = [];
        $dynamic = false;
        foreach ($types as $i => $t) {
            [$h, $tl] = self::encodeOne($t, $values[$i] ?? null);
            $heads[] = $h; $tails[] = $tl;
            if ($tl !== '') $dynamic = true;
        }
        if (! $dynamic) {
            return [implode('', $heads), ''];
        }
        $outHead = '';
        $outTail = '';
        $offset = count($types) * 32;
        foreach ($types as $i => $t) {
            if ($tails[$i] === '') {
                $outHead .= $heads[$i];
            } else {
                $outHead .= self::encUint($offset);
                $outTail .= $heads[$i].$tails[$i];
                $offset += strlen($heads[$i].$tails[$i]);
            }
        }
        return [$outHead, $outTail];
    }

    /** Decode a tuple; returns [values, newOffset] */
    private static function decodeTuple(array $types, string $bin, int $offset): array
    {
        $values = [];
        $pointers = [];
        $start = $offset;
        foreach ($types as $t) {
            if (self::isDynamic($t)) {
                $ptrWord = substr($bin, $offset, 32);
                $ptr = hexdec(bin2hex($ptrWord));
                $pointers[] = $ptr;
                $values[] = null;
            } else {
                // static types: if tuple, decode inline from current offset
                if (str_starts_with($t, '(')) {
                    $inner = rtrim(substr($t, 1), ')');
                    $innerTypes = self::splitTupleTypes($inner);
                    [$val] = self::decodeTuple($innerTypes, $bin, $offset);
                    $values[] = $val;
                } else {
                    $values[] = self::decodeStatic($t, substr($bin, $offset, 32));
                }
            }
            $offset += 32;
        }
        // decode dynamics
        foreach ($types as $i => $t) {
            if (! self::isDynamic($t)) continue;
            $values[$i] = self::decodeDynamic($t, $bin, $start + ($pointers[$i] ?? 0));
        }
        return [$values, $offset];
    }

    private static function decodeStatic(string $type, string $word)
    {
        if ($type === 'bool') return (bool) (ord(substr($word, -1)) & 1);
        if (preg_match('/^uint(\d+)?$/', $type)) {
            $hex = ltrim(bin2hex($word), '0');
            if ($hex === '') return '0';
            if (function_exists('gmp_init')) {
                return gmp_strval(gmp_init($hex, 16), 10);
            }
            // Fallback (limited precision): cast to string to preserve expectation type
            return (string) hexdec($hex);
        }
        if (preg_match('/^int(\d+)?$/', $type)) {
            // Minimal: treat as unsigned for now and return decimal string
            $hex = ltrim(bin2hex($word), '0');
            if ($hex === '') return '0';
            if (function_exists('gmp_init')) {
                return gmp_strval(gmp_init($hex, 16), 10);
            }
            return (string) hexdec($hex);
        }
        if ($type === 'address') return '0x'.substr(bin2hex(substr($word, -20)), 0, 40);
        if (preg_match('/^bytes(\d+)$/', $type, $m)) return '0x'.substr(bin2hex(substr($word, 0, (int)$m[1])), 0, (int)$m[1]*2);
        // tuple/static arrays handled in higher-level decode; strings/bytes are dynamic
        return '0x'.bin2hex($word);
    }

    private static function decodeDynamic(string $type, string $bin, int $offset)
    {
        if ($type === 'string') {
            $len = hexdec(bin2hex(substr($bin, $offset, 32)));
            $data = substr($bin, $offset + 32, $len);
            return $data;
        }
        if ($type === 'bytes') {
            $len = hexdec(bin2hex(substr($bin, $offset, 32)));
            $data = substr($bin, $offset + 32, $len);
            return '0x'.bin2hex($data);
        }
        if (str_starts_with($type, '(')) {
            // tuple dynamic encoded inline
            $inner = rtrim(substr($type, 1), ')');
            $types = self::splitTupleTypes($inner);
            return self::decodeTuple($types, $bin, $offset)[0];
        }
        if (str_ends_with($type, '[]')) {
            $elemType = substr($type, 0, -2);
            $len = hexdec(bin2hex(substr($bin, $offset, 32)));
            $offset += 32;
            $out = [];
            for ($i = 0; $i < $len; $i++) {
                if (self::isDynamic($elemType)) {
                    $ptr = hexdec(bin2hex(substr($bin, $offset + $i*32, 32)));
                    $out[] = self::decodeDynamic($elemType, $bin, $offset + $ptr);
                } else {
                    $out[] = self::decodeStatic($elemType, substr($bin, $offset + $i*32, 32));
                }
            }
            return $out;
        }
        // static arrays: handled at higher level, but provide a simple path
        if (preg_match('/\[(\d+)\]$/', $type, $m)) {
            $elemType = substr($type, 0, -(strlen($m[0])));
            $len = (int) $m[1];
            $out = [];
            for ($i = 0; $i < $len; $i++) {
                $out[] = self::decodeStatic($elemType, substr($bin, $offset + $i*32, 32));
            }
            return $out;
        }
        throw new \InvalidArgumentException('Unsupported dynamic type: '.$type);
    }

    /** Split tuple inner types at top-level commas */
    private static function splitTupleTypes(string $inner): array
    {
        $types = [];
        $buf = '';
        $depth = 0;
        $len = strlen($inner);
        for ($i=0; $i<$len; $i++) {
            $ch = $inner[$i];
            if ($ch === '(') $depth++;
            if ($ch === ')') $depth--;
            if ($ch === ',' && $depth === 0) {
                $types[] = $buf; $buf = '';
            } else {
                $buf .= $ch;
            }
        }
        if ($buf !== '') $types[] = $buf;
        return array_map('trim', $types);
    }
}
