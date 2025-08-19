<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Enums\WalletType;
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
            'wallet_type' => WalletType::CUSTODIAL,
            'owner_id' => null,
            'protocol' => \Roberts\Web3Laravel\Enums\BlockchainProtocol::EVM,
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

    public function custodial(): self
    {
        return $this->state(fn () => [
            'wallet_type' => WalletType::CUSTODIAL,
            'key' => '0x'.strtolower(bin2hex(random_bytes(32))),
        ]);
    }

    public function shared(): self
    {
        return $this->state(fn () => [
            'wallet_type' => WalletType::SHARED,
            'key' => '0x'.strtolower(bin2hex(random_bytes(32))),
        ]);
    }

    public function external(): self
    {
        return $this->state(fn () => [
            'wallet_type' => WalletType::EXTERNAL,
            'key' => null, // External wallets don't store private keys
        ]);
    }
}
