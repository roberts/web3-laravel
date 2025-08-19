<?php

namespace Roberts\Web3Laravel\Protocols\Sui;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\TransactionCostEstimator as EstimatorContract;

class TransactionCostEstimator implements EstimatorContract
{
    public function __construct(private SuiJsonRpcClient $rpc) {}

    public function estimateAndPopulate(Transaction $tx, Wallet $wallet): array
    {
        $price = 1;
        try { $price = max(1, $this->rpc->getReferenceGasPrice()); } catch (\Throwable) {}
        $computeUnit = 1000; // placeholder compute budget
        $fee = (string) ($price * $computeUnit);
        $amount = (string) ($tx->value ?? '0');
        $total = $this->addDec($amount, $fee);
        $meta = (array) ($tx->meta ?? []);
        $meta['sui'] = array_merge((array) ($meta['sui'] ?? []), [
            'referenceGasPrice' => $price,
            'estimatedGas' => $computeUnit,
        ]);
        $tx->meta = $meta;

        return [
            'total_required' => $total,
            'unit' => 'mist',
            'details' => ['gas_price' => $price, 'gas' => $computeUnit],
        ];
    }

    private function addDec(string $a, string $b): string
    {
        $a = ltrim($a, '+'); $b = ltrim($b, '+');
        $carry = 0; $res = ''; $i = strlen($a)-1; $j = strlen($b)-1;
        while ($i>=0 || $j>=0 || $carry) {
            $da = $i>=0 ? ord($a[$i]) - 48 : 0; $db = $j>=0 ? ord($b[$j]) - 48 : 0; $sum = $da+$db+$carry;
            $res = chr(($sum % 10)+48).$res; $carry = intdiv($sum,10); $i--; $j--;
        }
        return ltrim($res, '0') === '' ? '0' : ltrim($res, '0');
    }
}
