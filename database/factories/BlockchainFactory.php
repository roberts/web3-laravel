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
            'protocol' => 'evm',
            'supports_eip1559' => true,
            'native_symbol' => 'ETH',
            'native_decimals' => 18,
            'rpc_alternates' => null,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function baseMainnet(): self
    {
        return $this->state([
            'name' => 'Base',
            'abbreviation' => 'BASE',
            'chain_id' => 8453,
            'rpc' => 'https://mainnet.base.org',
            'supports_eip1559' => true,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function legacyNetwork(): self
    {
        return $this->state([
            'supports_eip1559' => false,
        ]);
    }
}
