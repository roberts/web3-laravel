<?php

namespace Roberts\Web3Laravel\Services;

use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Web3Laravel;
use Roberts\Web3Laravel\Support\Rlp;
use Elliptic\EC;
use Web3\Utils as Web3Utils;

class TransactionService
{
    public function __construct(
        protected Web3Laravel $web3
    ) {}

    /**
     * Build, sign (legacy or EIP-155), and send a raw transaction.
     * Minimal support: legacy gasPrice or EIP-155 (pre-1559) style. 1559 fields can be passed but not signed here yet.
     *
     * @param Wallet $from Wallet holding the private key
     * @param array $tx [to (hex), value (hex|int), data (hex), gas (int), gasPrice (int), nonce (int), chainId (int)]
     * @return string tx hash (0x...)
     */
    public function sendRaw(Wallet $from, array $tx): string
    {
        // Resolve chain & client
        $client = $from->web3();
        $eth = $client->eth;

        // Fetch missing fields
        $nonce = $tx['nonce'] ?? $this->ethCall($eth, 'getTransactionCount', [strtolower($from->address), 'pending']);
        $gasPrice = $tx['gasPrice'] ?? $this->ethCall($eth, 'gasPrice');
        $gasLimit = $tx['gas'] ?? 21000;
        $to = $tx['to'] ?? null;
        $value = $tx['value'] ?? 0;
        $data = $tx['data'] ?? '0x';
        $chainId = $tx['chainId'] ?? ($from->blockchain->chain_id ?? config('web3-laravel.default_chain_id'));

        // Switch to EIP-1559 if fields present
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

        // Normalize
        $toHex = $to ? Web3Utils::toHex($to) : '';
        $valueHex = is_string($value) ? $value : Web3Utils::toHex($value, true);
        $dataHex = Web3Utils::isZeroPrefixed($data) ? $data : ('0x' . ltrim($data, 'x'));

        if (!class_exists('Web3p\\EthereumTx\\Transaction')) {
            throw new \RuntimeException('Signing not available. Please require web3p/ethereum-tx.');
        }

        $txData = [
            'nonce' => Web3Utils::toHex($nonce, true),
            'gasPrice' => Web3Utils::toHex($gasPrice, true),
            'gas' => Web3Utils::toHex($gasLimit, true),
            'gasLimit' => Web3Utils::toHex($gasLimit, true),
            'to' => $toHex ?: '',
            'value' => $valueHex,
            'data' => $dataHex,
            'chainId' => $chainId,
        ];

        $txObj = new \Web3p\EthereumTx\Transaction($txData);
        $privKeyHex = $from->decryptKey() ?? '';
        if ($privKeyHex === '') {
            throw new \RuntimeException('Wallet has no private key available for signing.');
        }
        $raw = $txObj->sign($privKeyHex); // hex without 0x
        $rawHex = '0x' . ltrim($raw, '0x');

        $txHash = $eth->sendRawTransaction($rawHex);
        return $txHash;
    }

    protected function calculateV(int $recId, int $chainId): int
    {
        // EIP-155: v = recId + 35 + chainId * 2
        return $recId + 35 + ($chainId * 2);
    }

    // EIP-1559 type 0x02 path
    protected function sendEip1559(Wallet $from, array $tx): string
    {
        $client = $from->web3();
        $eth = $client->eth;

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
                $priority = $this->ethCall($eth, 'maxPriorityFeePerGas');
            } catch (\Throwable) {
                $priority = Web3Utils::toHex(1_000_000_000, true); // 1 gwei
            }
        }
        $maxFee = $tx['maxFeePerGas'] ?? null;
        if ($maxFee === null) {
            try {
                $gp = $this->ethCall($eth, 'gasPrice');
                $maxFee = is_string($gp) ? $gp : Web3Utils::toHex($gp, true);
            } catch (\Throwable) {
                $maxFee = $priority;
            }
        }

        // Normalize hex
        $nonceHex = is_string($nonce) ? $nonce : Web3Utils::toHex($nonce, true);
        $toHex = $to ? Web3Utils::toHex($to) : '';
        $valueHex = is_string($value) ? $value : Web3Utils::toHex($value, true);
        $dataHex = Web3Utils::isZeroPrefixed($data) ? $data : ('0x' . ltrim($data, 'x'));
        $priorityHex = is_string($priority) ? $priority : Web3Utils::toHex($priority, true);
        $maxFeeHex = is_string($maxFee) ? $maxFee : Web3Utils::toHex($maxFee, true);
        $gasHex = Web3Utils::toHex($gasLimit, true);

        // RLP list for signing: [chainId, nonce, maxPriorityFeePerGas, maxFeePerGas, gas, to, value, data, accessList]
        $signItems = [
            Rlp::encodeInt($chainId),
            Rlp::encodeHex(Web3Utils::stripZero($nonceHex)),
            Rlp::encodeHex(Web3Utils::stripZero($priorityHex)),
            Rlp::encodeHex(Web3Utils::stripZero($maxFeeHex)),
            Rlp::encodeHex(Web3Utils::stripZero($gasHex)),
            $toHex === '' ? Rlp::encodeString('') : Rlp::encodeHex(Web3Utils::stripZero($toHex)),
            Rlp::encodeHex(Web3Utils::stripZero($valueHex)),
            Rlp::encodeHex(Web3Utils::stripZero($dataHex)),
            $this->encodeAccessList($accessList),
        ];

        $rlpForSign = Rlp::encodeList($signItems);
        $payload = "\x02" . $rlpForSign;
        $hashHex = Web3Utils::sha3('0x' . bin2hex($payload));

        $ec = new EC('secp256k1');
        $priv = $from->decryptKey() ?? '';
        if ($priv === '') {
            throw new \RuntimeException('Wallet has no private key available for signing.');
        }
        $kp = $ec->keyFromPrivate($priv, 'hex');
        $sig = $kp->sign($hashHex, ['canonical' => true]);
        $rHex = $sig->r->toString(16);
        $sHex = $sig->s->toString(16);
        $yParity = (int) $sig->recoveryParam; // 0 or 1

        $signedItems = array_merge($signItems, [
            Rlp::encodeInt($yParity),
            Rlp::encodeHex($rHex),
            Rlp::encodeHex($sHex),
        ]);
        $signedRlp = Rlp::encodeList($signedItems);
        $rawHex = '0x02' . bin2hex($signedRlp);

        $txHash = $eth->sendRawTransaction($rawHex);
        return $txHash;
    }

    private function ethCall($eth, string $method, array $args = [])
    {
        $result = null;
        $error = null;
        $cb = function ($err, $res) use (&$error, &$result) {
            $error = $err;
            $result = $res;
        };
        $args[] = $cb;
        $eth->{$method}(...$args);
        if ($error) {
            if ($error instanceof \Throwable) {
                throw $error;
            }
            throw new \RuntimeException('eth call error for ' . $method);
        }
        return $result;
    }

    private function encodeAccessList(array $accessList): string
    {
        $entries = [];
        foreach ($accessList as $entry) {
            $address = $entry['address'] ?? ($entry[0] ?? '');
            $storageKeys = $entry['storageKeys'] ?? ($entry['keys'] ?? ($entry[1] ?? []));
            $encodedKeys = [];
            foreach ((array) $storageKeys as $key) {
                $encodedKeys[] = Rlp::encodeHex(Web3Utils::stripZero($key));
            }
            $entries[] = Rlp::encodeList([
                $address ? Rlp::encodeHex(Web3Utils::stripZero($address)) : Rlp::encodeString(''),
                Rlp::encodeList($encodedKeys),
            ]);
        }
        return Rlp::encodeList($entries);
    }
}
