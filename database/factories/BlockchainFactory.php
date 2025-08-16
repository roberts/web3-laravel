<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\Blockchain;

class BlockchainFactory extends Factory
{
    protected $model = Blockchain::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'abbreviation' => strtoupper($this->faker->lexify('???')),
            'chain_id' => $this->faker->numberBetween(1, 10_000_000),
            'rpc' => $this->faker->url(),
            'scanner' => $this->faker->optional()->url(),
            'evm' => true,
            'supports_eip1559' => true,
            'native_symbol' => 'ETH',
            'native_decimals' => 18,
            'rpc_alternates' => null,
            'is_active' => true,
            'is_default' => false,
        ];
    }
}
