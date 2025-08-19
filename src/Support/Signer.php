<?php

namespace Roberts\Web3Laravel\Support;

use Elliptic\EC;

class Signer
{
    /**
     * Sign a legacy (type 0) EVM transaction with EIP-155 protection when chainId provided.
     * Input values may be hex (0x) or ints/strings; returns [raw => 0x..., txHash => 0x...].
     * Expected keys: nonce, gasPrice, gas|gasLimit, to, value, data, chainId?
     */
    public static function signLegacy(array $tx, string $privateKeyHex): array
    {
        $nonceHex = is_string($tx['nonce'] ?? '') ? ($tx['nonce'] ?? '') : Hex::toHex($tx['nonce'] ?? 0, true);
        $gasPriceHex = is_string($tx['gasPrice'] ?? '') ? ($tx['gasPrice'] ?? '') : Hex::toHex($tx['gasPrice'] ?? 0, true);
        $gasHex = is_string($tx['gas'] ?? ($tx['gasLimit'] ?? 21000)) ? ($tx['gas'] ?? ($tx['gasLimit'] ?? 21000)) : Hex::toHex($tx['gas'] ?? ($tx['gasLimit'] ?? 21000), true);
        $toHex = ($tx['to'] ?? '') ? Hex::toHex($tx['to']) : '';
        $valueHex = is_string($tx['value'] ?? '0x0') ? ($tx['value'] ?? '0x0') : Hex::toHex($tx['value'] ?? 0, true);
        $dataHex = is_string($tx['data'] ?? '0x') ? ($tx['data'] ?? '0x') : Hex::toHex($tx['data'] ?? '', false);
        $chainId = isset($tx['chainId']) ? (int) $tx['chainId'] : null;

        $signItems = [
            Rlp::encodeHex(Hex::stripZero($nonceHex)),
            Rlp::encodeHex(Hex::stripZero($gasPriceHex)),
            Rlp::encodeHex(Hex::stripZero($gasHex)),
            $toHex === '' ? Rlp::encodeString('') : Rlp::encodeHex(Hex::stripZero($toHex)),
            Rlp::encodeHex(Hex::stripZero($valueHex)),
            Rlp::encodeHex(Hex::stripZero($dataHex)),
        ];
        if ($chainId !== null) {
            $signItems[] = Rlp::encodeInt($chainId);
            $signItems[] = Rlp::encodeString('');
            $signItems[] = Rlp::encodeString('');
        }

        $rlpForSign = Rlp::encodeList($signItems);
        $hashHex = Keccak::hash('0x'.bin2hex($rlpForSign));

        $ec = new EC('secp256k1');
        $kp = $ec->keyFromPrivate(Hex::stripZero($privateKeyHex), 'hex');
        $sig = $kp->sign($hashHex, ['canonical' => true]);
        $rHex = $sig->r->toString(16);
        $sHex = $sig->s->toString(16);
        $recId = (int) $sig->recoveryParam; // 0 or 1

        $v = $chainId !== null ? ($recId + 35 + ($chainId * 2)) : ($recId + 27);

        $signedItems = [
            Rlp::encodeHex(Hex::stripZero($nonceHex)),
            Rlp::encodeHex(Hex::stripZero($gasPriceHex)),
            Rlp::encodeHex(Hex::stripZero($gasHex)),
            $toHex === '' ? Rlp::encodeString('') : Rlp::encodeHex(Hex::stripZero($toHex)),
            Rlp::encodeHex(Hex::stripZero($valueHex)),
            Rlp::encodeHex(Hex::stripZero($dataHex)),
            Rlp::encodeInt($v),
            Rlp::encodeHex($rHex),
            Rlp::encodeHex($sHex),
        ];
        $rawRlp = Rlp::encodeList($signedItems);
        $rawHex = '0x'.bin2hex($rawRlp);
        $txHash = Keccak::hash('0x'.bin2hex($rawRlp));

        return ['raw' => $rawHex, 'txHash' => $txHash];
    }

    /** Sign EIP-1559 (type 0x02) transaction. Returns [raw, txHash]. */
    public static function signEip1559(array $tx, string $privateKeyHex): array
    {
        $chainId = (int) ($tx['chainId'] ?? 1);
        $nonceHex = is_string($tx['nonce'] ?? '') ? ($tx['nonce'] ?? '') : Hex::toHex($tx['nonce'] ?? 0, true);
        $priorityHex = is_string($tx['maxPriorityFeePerGas'] ?? '') ? ($tx['maxPriorityFeePerGas'] ?? '') : Hex::toHex($tx['maxPriorityFeePerGas'] ?? 0, true);
        $maxFeeHex = is_string($tx['maxFeePerGas'] ?? '') ? ($tx['maxFeePerGas'] ?? '') : Hex::toHex($tx['maxFeePerGas'] ?? 0, true);
        $gasHex = is_string($tx['gas'] ?? ($tx['gasLimit'] ?? 21000)) ? ($tx['gas'] ?? ($tx['gasLimit'] ?? 21000)) : Hex::toHex($tx['gas'] ?? ($tx['gasLimit'] ?? 21000), true);
        $toHex = ($tx['to'] ?? '') ? Hex::toHex($tx['to']) : '';
        $valueHex = is_string($tx['value'] ?? '0x0') ? ($tx['value'] ?? '0x0') : Hex::toHex($tx['value'] ?? 0, true);
        $dataHex = is_string($tx['data'] ?? '0x') ? ($tx['data'] ?? '0x') : Hex::toHex($tx['data'] ?? '', false);
        $accessList = (array) ($tx['accessList'] ?? []);

        $signItems = [
            Rlp::encodeInt($chainId),
            Rlp::encodeHex(Hex::stripZero($nonceHex)),
            Rlp::encodeHex(Hex::stripZero($priorityHex)),
            Rlp::encodeHex(Hex::stripZero($maxFeeHex)),
            Rlp::encodeHex(Hex::stripZero($gasHex)),
            $toHex === '' ? Rlp::encodeString('') : Rlp::encodeHex(Hex::stripZero($toHex)),
            Rlp::encodeHex(Hex::stripZero($valueHex)),
            Rlp::encodeHex(Hex::stripZero($dataHex)),
            self::encodeAccessList($accessList),
        ];
        $rlpForSign = Rlp::encodeList($signItems);
        $payload = "\x02".$rlpForSign;
        $hashHex = Keccak::hash('0x'.bin2hex($payload));

        $ec = new EC('secp256k1');
        $kp = $ec->keyFromPrivate(Hex::stripZero($privateKeyHex), 'hex');
        $sig = $kp->sign($hashHex, ['canonical' => true]);
        $rHex = $sig->r->toString(16);
        $sHex = $sig->s->toString(16);
        $yParity = (int) $sig->recoveryParam; // 0/1 per EIP-1559

        $signedItems = array_merge($signItems, [
            Rlp::encodeInt($yParity),
            Rlp::encodeHex($rHex),
            Rlp::encodeHex($sHex),
        ]);
        $signedRlp = Rlp::encodeList($signedItems);
        $rawHex = '0x02'.bin2hex($signedRlp);
        $txHash = Keccak::hash('0x'.bin2hex("\x02".$signedRlp));

        return ['raw' => $rawHex, 'txHash' => $txHash];
    }

    private static function encodeAccessList(array $accessList): string
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
