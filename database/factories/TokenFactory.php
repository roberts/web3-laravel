<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;

/**
 * @extends Factory<Token>
 */
class TokenFactory extends Factory
{
    protected $model = Token::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true).' Token';
        $symbol = strtoupper($this->faker->lexify('???'));

        return [
            'contract_id' => Contract::factory(),
            'symbol' => $symbol,
            'name' => $name,
            'decimals' => 18,
            'total_supply' => (string) $this->faker->numberBetween(1000000, 1000000000).str_repeat('0', 18),
            'metadata' => [
                'icon_url' => $this->faker->imageUrl(64, 64),
                'description' => $this->faker->sentence(),
                'website' => $this->faker->url(),
                'social' => [
                    'twitter' => '@'.$this->faker->userName(),
                    'telegram' => 't.me/'.$this->faker->userName(),
                    'discord' => 'discord.gg/'.$this->faker->lexify('???????'),
                ],
                'deployer_metadata' => [
                    'launch_date' => $this->faker->dateTimeThisYear(),
                    'initial_liquidity' => $this->faker->numberBetween(1, 100).' ETH',
                    'platform' => 'Web3Laravel Deployer',
                ],
            ],
        ];
    }

    public function withSymbol(string $symbol): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => $symbol,
        ]);
    }

    public function withDecimals(int $decimals): static
    {
        return $this->state(fn (array $attributes) => [
            'decimals' => $decimals,
        ]);
    }

    public function withSupply(string $supply): static
    {
        return $this->state(fn (array $attributes) => [
            'total_supply' => $supply,
        ]);
    }

    public function stablecoin(): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol' => 'USDC',
            'name' => 'USD Coin',
            'decimals' => 6,
            'total_supply' => '50000000000000', // 50B USDC with 6 decimals
        ]);
    }
}
