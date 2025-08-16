<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $wallet = Wallet::factory()->create();

        return [
            'wallet_id' => $wallet->id,
            'blockchain_id' => $wallet->blockchain_id,
            'to' => '0x'.$this->faker->regexify('[a-f0-9]{40}'),
            'from' => $wallet->address,
            'value' => '0',
            'gas_limit' => 21000,
            'fee_max' => '0x77359400',
            'priority_max' => '0x3b9aca00',
            'is_1559' => true,
            'chain_id' => $wallet->blockchain?->chain_id,
            'status' => 'pending',
        ];
    }

    public function legacy(): self
    {
        return $this->state(fn () => [
            'is_1559' => false,
            'gwei' => '0x3b9aca00',
            'fee_max' => null,
            'priority_max' => null,
        ]);
    }
}
