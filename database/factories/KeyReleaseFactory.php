<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\KeyRelease;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * @extends Factory<KeyRelease>
 */
class KeyReleaseFactory extends Factory
{
    protected $model = KeyRelease::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'user_id' => 1, // Default to user ID 1
            'released_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'security_context' => [
                'timestamp' => now()->toISOString(),
                'session_id' => $this->faker->uuid(),
                'csrf_token' => 'present',
                'authenticated_user_id' => 1,
            ],
        ];
    }

    public function recent(): self
    {
        return $this->state(fn () => [
            'released_at' => now()->subMinutes($this->faker->numberBetween(1, 30)),
        ]);
    }

    public function forWallet(Wallet $wallet): self
    {
        return $this->state(fn () => [
            'wallet_id' => $wallet->id,
        ]);
    }

    public function forUser($user): self
    {
        return $this->state(fn () => [
            'user_id' => $user->id ?? $user,
        ]);
    }
}
