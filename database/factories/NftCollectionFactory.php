<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\NftCollection;

/**
 * @extends Factory<NftCollection>
 */
class NftCollectionFactory extends Factory
{
    protected $model = NftCollection::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'name' => $this->faker->words(3, true).' Collection',
            'symbol' => strtoupper($this->faker->lexify('???')),
            'description' => $this->faker->text(),
            'image_url' => $this->faker->imageUrl(400, 400),
            'banner_url' => $this->faker->imageUrl(800, 400),
            'external_url' => $this->faker->url(),
            'standard' => $this->faker->randomElement([TokenType::ERC721, TokenType::ERC1155]),
            'total_supply' => $this->faker->randomNumber(4),
            'floor_price' => $this->faker->randomNumber(6),
            'metadata' => [
                'verified' => $this->faker->boolean(),
                'featured' => $this->faker->boolean(),
                'category' => $this->faker->randomElement(['Art', 'Gaming', 'Music', 'Photography', 'Sports']),
            ],
        ];
    }

    public function erc721(): static
    {
        return $this->state(fn (array $attributes) => [
            'standard' => TokenType::ERC721,
        ]);
    }

    public function erc1155(): static
    {
        return $this->state(fn (array $attributes) => [
            'standard' => TokenType::ERC1155,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'verified' => true,
            ]),
        ]);
    }

    public function withFloorPrice(string $price): static
    {
        return $this->state(fn (array $attributes) => [
            'floor_price' => $price,
        ]);
    }
}
