<?php

namespace Roberts\Web3Laravel\Protocols\Evm;

use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Contracts\TransactionCostEstimator as EstimatorContract;
use Roberts\Web3Laravel\Services\TransactionService;
use Roberts\Web3Laravel\Support\Hex;

class TransactionCostEstimator implements EstimatorContract
{
    public function __construct(private TransactionService $svc) {}

    public function estimateAndPopulate(Transaction $tx, Wallet $wallet): array
    {
        // Gas limit
        if (empty($tx->gas_limit)) {
            try {
                $estimateHex = $this->svc->estimateGas($wallet, array_filter([
                    'to' => $tx->to,
                    'value' => $tx->value,
                    'data' => $tx->data,
                ], fn ($v) => $v !== null && $v !== ''));
                $gasInt = str_starts_with($estimateHex, '0x') ? hexdec(substr($estimateHex, 2)) : (int) $estimateHex;
                $tx->gas_limit = (int) ceil($gasInt * 1.12);
            } catch (\Throwable) {
                $tx->gas_limit = 21000;
                $tx->error = trim((string) (($tx->error ? $tx->error.' ' : '').'gas_estimate_failed'));
            }
        }

        // 1559 defaults
        if ($tx->is_1559 === null) { $tx->is_1559 = true; }
        if ($tx->is_1559) {
            if (empty($tx->priority_max) || empty($tx->fee_max)) {
                try {
                    $fees = $this->svc->suggestFees($wallet);
                    $tx->priority_max = $tx->priority_max ?: $fees['priority'];
                    $tx->fee_max = $tx->fee_max ?: $fees['max'];
                } catch (\Throwable) {
                    $tx->priority_max = $tx->priority_max ?: Hex::toHex(1_000_000_000, true);
                    $tx->fee_max = $tx->fee_max ?: $tx->priority_max;
                }
            }
        } else {
            if (empty($tx->gwei)) {
                try {
                    $gp = $wallet->gasPrice();
                    $tx->gwei = $gp;
                } catch (\Throwable) {
                    $tx->gwei = Hex::toHex(1_000_000_000, true);
                }
            }
        }

        // Compute total required in wei: value + gas*price
        $valueHex = $tx->value === null ? '0x0' : (is_string($tx->value) ? $tx->value : Hex::toHex($tx->value, true));
        $gas = (int) ($tx->gas_limit ?? 0);
        $priceHex = $tx->is_1559 ? ($tx->fee_max ?? null) : ($tx->gwei ?? null);
        $gasCostHex = ($gas > 0 && $priceHex) ? $this->mulHexByInt($priceHex, $gas) : '0x0';
        $total = $this->addHex($valueHex, $gasCostHex);

        return [
            'total_required' => ltrim($total, '0x') === '' ? '0' : (string) hexdec(substr($total, 2)),
            'unit' => 'wei',
            'details' => [
                'value' => $valueHex,
                'gas' => $tx->gas_limit,
                'price' => $priceHex,
                'gas_cost' => $gasCostHex,
            ],
        ];
    }

    protected function strip0x(string $hex): string { return str_starts_with($hex, '0x') ? substr($hex, 2) : $hex; }
    protected function addHex(string $a, string $b): string
    {
        $a = ltrim($this->strip0x($a), '0');
        $b = ltrim($this->strip0x($b), '0');
        $carry = 0; $res = ''; $i = strlen($a)-1; $j = strlen($b)-1;
        while ($i>=0 || $j>=0 || $carry) {
            $da = $i>=0 ? hexdec($a[$i]) : 0; $db = $j>=0 ? hexdec($b[$j]) : 0; $sum = $da+$db+$carry;
            $res = dechex($sum % 16).$res; $carry = intdiv($sum,16); $i--; $j--;
        }
        return '0x'.($res === '' ? '0' : ltrim($res, '0'));
    }
    protected function mulHexByInt(string $hex, int $mult): string
    {
        $hex = ltrim($this->strip0x($hex), '0'); if ($mult === 0 || $hex === '') { return '0x0'; }
        $carry = 0; $res = '';
        for ($i = strlen($hex)-1; $i>=0; $i--) { $d = hexdec($hex[$i]); $prod = $d*$mult+$carry; $res = dechex($prod%16).$res; $carry = intdiv($prod,16);} 
        while ($carry>0) { $res = dechex($carry%16).$res; $carry = intdiv($carry,16);} 
        return '0x'.ltrim($res, '0');
    }
}
