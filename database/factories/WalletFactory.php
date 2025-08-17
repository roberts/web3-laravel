<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'address' => '0x'.strtolower(bin2hex(random_bytes(20))),
            'key' => '0x'.strtolower(bin2hex(random_bytes(32))), // will be encrypted by mutator
            'owner_type' => null,
            'owner_id' => null,
            'blockchain_id' => Blockchain::factory(),
            'is_active' => true,
            'last_used_at' => null,
            'meta' => null,
        ];
    }

    public function withoutKey(): self
    {
        return $this->state(fn () => ['key' => null]);
    }

    public function withKey(?string $hex = null): self
    {
        return $this->state(fn () => ['key' => $hex ?? ('0x'.strtolower(bin2hex(random_bytes(32))))]);
    }
}
