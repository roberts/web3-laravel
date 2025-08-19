<?php

namespace Roberts\Web3Laravel\Protocols\Solana;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\TransactionCostEstimator as EstimatorContract;
use Tuupola\Base58;

class TransactionCostEstimator implements EstimatorContract
{

    public function estimateAndPopulate(Transaction $tx, Wallet $wallet): array
    {
        // Solana fees are per-signature and cluster dependent; simplify using 0.000005 SOL per sig (5000 lamports) as a safe floor.
        $lamports = (string) ($tx->value ?? '0');
        $fee = '5000';
        $total = $this->addDec($lamports, $fee);

        // No tx field updates needed for Solana here.
        return [
            'total_required' => $total,
            'unit' => 'lamports',
            'details' => ['fee_lamports' => $fee],
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
