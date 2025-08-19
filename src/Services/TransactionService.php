<?php

namespace Roberts\Web3Laravel\Services;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface;
use Roberts\Web3Laravel\Support\Hex;
use Roberts\Web3Laravel\Support\Rlp;
use Roberts\Web3Laravel\Support\Signer;
use InvalidArgumentException;

class TransactionService
{
    public function __construct() {}

    /** Estimate gas for a transaction using the wallet's network. */
    public function estimateGas(Wallet $from, array $tx, string $blockTag = 'latest'): string
    {
    if ($from->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            $payload = array_merge(['from' => strtolower($from->address)], $tx);

            return $evm->estimateGas($payload, $blockTag);
        }

    throw new InvalidArgumentException('estimateGas not supported for non-EVM protocols.');
    }

    /** Suggest EIP-1559 fee parameters (maxPriorityFeePerGas, maxFeePerGas) as hex strings. */
    public function suggestFees(Wallet $from): array
    {
        if ($from->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            try {
                $priority = $evm->maxPriorityFeePerGas();
            } catch (\Throwable) {
                $priority = Hex::toHex(1_000_000_000, true);
            }
            try {
                $gp = $evm->gasPrice();
            } catch (\Throwable) {
                $gp = $priority;
            }
            return ['priority' => is_string($priority) ? $priority : Hex::toHex((int) $priority, true), 'max' => is_string($gp) ? $gp : Hex::toHex((int) $gp, true)];
        }
    throw new InvalidArgumentException('suggestFees not supported for protocol');
    }

    /**
     * Build, sign (legacy or EIP-155), and send a raw transaction.
     * Minimal support: legacy gasPrice or EIP-155 (pre-1559) style. 1559 fields can be passed but not signed here yet.
     *
     * @param  Wallet  $from  Wallet holding the private key
     * @param  array  $tx  [to (hex), value (hex|int), data (hex), gas (int), gasPrice (int), nonce (int), chainId (int)]
     * @return string tx hash (0x...)
     */
    public function sendRaw(Wallet $from, array $tx): string
    {
        // Resolve chain & client
    if ($from->protocol->isEvm()) {
            /** @var EvmClientInterface $evm */
            $evm = app(EvmClientInterface::class);
            $nonce = $tx['nonce'] ?? $evm->getTransactionCount($from->address, 'pending');
            $gasPrice = $tx['gasPrice'] ?? $evm->gasPrice();
            // If no gas provided, estimate from node using provided fields
            $gasLimit = $tx['gas'] ?? $tx['gasLimit'] ?? null;
            $to = $tx['to'] ?? null;
            $value = $tx['value'] ?? 0;
            $data = $tx['data'] ?? '0x';
            $chainId = $tx['chainId'] ?? config('web3-laravel.default_chain_id');

            if ($gasLimit === null) {
                $est = $evm->estimateGas([
                    'from' => strtolower($from->address),
                    'to' => $to,
                    'value' => $value,
                    'data' => $data,
                ], 'latest');
                if (is_string($est) && str_starts_with($est, '0x')) {
                    $gasInt = hexdec(substr($est, 2));
                } else {
                    $gasInt = (int) $est;
                }
                $gasLimit = (int) ceil($gasInt * 1.12);
            }

            // EIP-1559 path detection
            $is1559 = isset($tx['maxFeePerGas']) || isset($tx['maxPriorityFeePerGas']) || (($tx['type'] ?? null) === 2);
            if ($is1559) {
                return $this->sendEip1559($from, [
                    'nonce' => $nonce,
                    'to' => $to,
                    'value' => $value,
                    'data' => $data,
                    'gas' => $tx['gas'] ?? $tx['gasLimit'] ?? $gasLimit,
                    'chainId' => $chainId,
                    'maxFeePerGas' => $tx['maxFeePerGas'] ?? null,
                    'maxPriorityFeePerGas' => $tx['maxPriorityFeePerGas'] ?? null,
                    'accessList' => $tx['accessList'] ?? [],
                ]);
            }

            $toHex = $to ? Hex::toHex($to) : '';
            $valueHex = is_string($value) ? $value : Hex::toHex($value, true);
            $dataHex = Hex::isZeroPrefixed($data) ? $data : ('0x'.ltrim($data, 'x'));

            $txData = [
                'nonce' => is_string($nonce) ? $nonce : Hex::toHex($nonce, true),
                'gasPrice' => is_string($gasPrice) ? $gasPrice : Hex::toHex($gasPrice, true),
                'gas' => is_string($gasLimit) ? $gasLimit : Hex::toHex($gasLimit, true),
                'gasLimit' => is_string($gasLimit) ? $gasLimit : Hex::toHex($gasLimit, true),
                'to' => $toHex ?: '',
                'value' => $valueHex,
                'data' => $dataHex,
                'chainId' => $chainId,
            ];
            $privKeyHex = $from->decryptKey() ?? '';
            if ($privKeyHex === '') {
                throw new \RuntimeException('Wallet has no private key available for signing.');
            }
            $signed = Signer::signLegacy($txData, $privKeyHex);
            return $evm->sendRawTransaction($signed['raw']);
        }
        // Non-EVM protocols are not supported here
        throw new InvalidArgumentException('sendRaw not supported for non-EVM protocols.');
    }

    protected function calculateV(int $recId, int $chainId): int
    {
        // EIP-155: v = recId + 35 + chainId * 2
        return $recId + 35 + ($chainId * 2);
    }

    // EIP-1559 type 0x02 path
    protected function sendEip1559(Wallet $from, array $tx): string
    {
        if (! $from->protocol->isEvm()) {
            throw new InvalidArgumentException('sendEip1559 not supported for non-EVM protocols.');
        }

        /** @var EvmClientInterface $evm */
        $evm = app(EvmClientInterface::class);

        $nonce = $tx['nonce'];
        $to = $tx['to'] ?? null;
        $value = $tx['value'] ?? 0;
        $data = $tx['data'] ?? '0x';
        $gasLimit = $tx['gas'] ?? 21000;
        $chainId = (int) $tx['chainId'];
        $accessList = $tx['accessList'] ?? [];

        $priority = $tx['maxPriorityFeePerGas'] ?? null;
        if ($priority === null) {
            try {
                $priority = $evm->maxPriorityFeePerGas();
            } catch (\Throwable) {
                $priority = Hex::toHex(1_000_000_000, true); // 1 gwei fallback
            }
        }

        $maxFee = $tx['maxFeePerGas'] ?? null;
        if ($maxFee === null) {
            try {
                $gp = $evm->gasPrice();
                $maxFee = $gp ?? $priority;
            } catch (\Throwable) {
                $maxFee = $priority;
            }
        }

        // Normalize for signing
        $signed = Signer::signEip1559([
            'chainId' => $chainId,
            'nonce' => $nonce,
            'maxPriorityFeePerGas' => $priority,
            'maxFeePerGas' => $maxFee,
            'gas' => $gasLimit,
            'to' => $to,
            'value' => $value,
            'data' => $data,
            'accessList' => $accessList,
        ], $from->decryptKey() ?? '');

        return $evm->sendRawTransaction($signed['raw']);
    }

    // Removed ethCall/web3.php path

    /** @phpstan-ignore-next-line method.unused */
    private function encodeAccessList(array $accessList): string
    {
        $entries = [];
        foreach ($accessList as $entry) {
            $address = $entry['address'] ?? ($entry[0] ?? '');
            $storageKeys = $entry['storageKeys'] ?? ($entry['keys'] ?? ($entry[1] ?? []));
            $encodedKeys = [];
            foreach ((array) $storageKeys as $key) {
                $encodedKeys[] = Rlp::encodeHex(Hex::stripZero($key));
            }
            $entries[] = Rlp::encodeList([
                $address ? Rlp::encodeHex(Hex::stripZero($address)) : Rlp::encodeString(''),
                Rlp::encodeList($encodedKeys),
            ]);
        }

        return Rlp::encodeList($entries);
    }
}
