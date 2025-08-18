<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletNft;

/**
 * @extends Factory<WalletNft>
 */
class WalletNftFactory extends Factory
{
    protected $model = WalletNft::class;

    public function definition(): array
    {
        $tokenId = (string) $this->faker->numberBetween(1, 10000);

        return [
            'wallet_id' => Wallet::factory(),
            'nft_collection_id' => NftCollection::factory(),
            'token_id' => $tokenId,
            'quantity' => '1',
            'metadata_uri' => "ipfs://QmHash{$tokenId}/metadata.json",
            'metadata' => [
                'name' => "Token #{$tokenId}",
                'description' => $this->faker->sentence(),
                'image' => "ipfs://QmHash{$tokenId}/image.png",
                'attributes' => $this->generateTraits(),
            ],
            'traits' => $this->generateTraits(),
            'rarity_rank' => $this->faker->numberBetween(1, 1000),
            'acquired_at' => $this->faker->dateTimeBetween('-1 year'),
        ];
    }

    protected function generateTraits(): array
    {
        $traitCount = $this->faker->numberBetween(3, 8);
        $traits = [];

        for ($i = 0; $i < $traitCount; $i++) {
            $traits[] = [
                'trait_type' => $this->faker->randomElement([
                    'Background', 'Eyes', 'Mouth', 'Hat', 'Clothes', 'Accessories',
                ]),
                'value' => $this->faker->randomElement([
                    'Common', 'Rare', 'Epic', 'Legendary', 'Mythic',
                ]),
                'rarity' => $this->faker->randomFloat(4, 0.0001, 0.5),
            ];
        }

        return $traits;
    }

    public function withTokenId(string $tokenId): static
    {
        return $this->state(fn (array $attributes) => [
            'token_id' => $tokenId,
            'metadata_uri' => "ipfs://QmHash{$tokenId}/metadata.json",
            'metadata' => [
                'name' => "Token #{$tokenId}",
                'description' => $this->faker->sentence(),
                'image' => "ipfs://QmHash{$tokenId}/image.png",
                'attributes' => $this->generateTraits(),
            ],
        ]);
    }

    public function erc1155(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => (string) $this->faker->numberBetween(1, 100),
        ]);
    }

    public function withQuantity(string $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }

    public function rare(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity_rank' => $this->faker->numberBetween(1, 50),
            'traits' => array_map(function ($trait) {
                $trait['rarity'] = $this->faker->randomFloat(4, 0.0001, 0.01);

                return $trait;
            }, $attributes['traits'] ?? []),
        ]);
    }

    public function common(): static
    {
        return $this->state(fn (array $attributes) => [
            'rarity_rank' => $this->faker->numberBetween(500, 1000),
            'traits' => array_map(function ($trait) {
                $trait['rarity'] = $this->faker->randomFloat(4, 0.1, 0.5);

                return $trait;
            }, $attributes['traits'] ?? []),
        ]);
    }
}
