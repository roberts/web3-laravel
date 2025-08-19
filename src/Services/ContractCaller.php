<?php

namespace Roberts\Web3Laravel\Services;

use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Support\Abi;

class ContractCaller
{
    public function __construct(protected EvmClientInterface $evm) {}

    /**
     * Encode a function call using ABI and perform eth_call.
     *
     * @param  array  $abi  ABI JSON array
     * @param  string  $to  Contract address
     * @param  string  $function  Function name
     * @param  array  $params  Function params
     * @param  string|null  $from  Optional from address
     * @param  string  $blockTag  default 'latest'
     * @return array decoded outputs
     */
    public function call(array $abi, string $to, string $function, array $params = [], ?string $from = null, string $blockTag = 'latest'): array
    {
    $data = $this->encodeCallData($abi, $function, $params);

        $tx = [
            'to' => $to,
            'data' => $data,
        ];
        if ($from) {
            $tx['from'] = $from;
        }

    $raw = $this->evm->call($tx, $blockTag);

        return $this->decodeCallResult($abi, $function, $raw);
    }

    /** Build 0x-prefixed data for a function call based on ABI and params. */
    public function encodeCallData(array $abi, string $function, array $params = []): string
    {
    return Abi::encodeFunctionCall($abi, $function, $params);
    }

    /** Decode a 0x-hex return payload into PHP values per ABI outputs. */
    public function decodeCallResult(array $abi, string $function, string $raw): array
    {
    $method = $this->findFunction($abi, $function);
    $outputs = $method['outputs'] ?? [];
    $outputTypes = $this->normalizeParamTypes($outputs);
    $decoded = (array) Abi::decodeParameters($outputTypes, $raw);
    return $this->deepNormalize($decoded);
    }

    protected function findFunction(array $abi, string $name): array
    {
        foreach ($abi as $item) {
            if (($item['type'] ?? null) === 'function' && ($item['name'] ?? '') === $name) {
                return $item;
            }
        }
        throw new \InvalidArgumentException("Function {$name} not found in ABI.");
    }

    /** Normalize ABI param entries to ethabi type strings, supporting tuple(s) and arrays. */
    protected function normalizeParamTypes(array $params): array
    {
        $types = [];
        foreach ($params as $param) {
            $types[] = $this->normalizeType($param);
        }

        return $types;
    }

    protected function normalizeType(array $param): string
    {
        $type = (string) ($param['type'] ?? 'bytes');
        // Handle tuple and tuple arrays
        if (str_starts_with($type, 'tuple')) {
            $suffix = substr($type, 5); // may be '', '[]', '[2]', etc
            $components = $param['components'] ?? [];
            $inner = [];
            foreach ($components as $c) {
                $inner[] = $this->normalizeType($c);
            }

            return '('.implode(',', $inner).')'.$suffix;
        }

        return $type;
    }

    /** Recursively normalize decoded values: objects with toString to strings, arrays deep. */
    protected function deepNormalize($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->deepNormalize($v);
            }

            return $out;
        }
        if (is_object($value) && method_exists($value, 'toString')) {
            return (string) $value->toString();
        }

        return $value;
    }
}
