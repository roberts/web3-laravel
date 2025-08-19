<?php

namespace Roberts\Web3Laravel\Protocols\Hedera;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\TransactionCostEstimator as EstimatorContract;

class TransactionCostEstimator implements EstimatorContract
{
    public function estimateAndPopulate(Transaction $tx, Wallet $wallet): array
    {
        // Placeholder: 100000 tinybars
        $fee = '100000';
        $amount = (string) ($tx->value ?? '0');
        $total = $this->addDec($amount, $fee);

        return [
            'total_required' => $total,
            'unit' => 'tinybar',
            'details' => ['fee_tinybar' => $fee],
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
