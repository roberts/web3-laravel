<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'blockchain_id' => Blockchain::factory(),
            'address' => '0x'.strtolower(bin2hex(random_bytes(20))),
            'creator' => '0x'.strtolower(bin2hex(random_bytes(20))),
            'abi' => null,
        ];
    }

    public function withAbi(?array $abi = null): self
    {
        return $this->state([
            'abi' => $abi ?? [
                ['type' => 'function', 'name' => 'symbol', 'inputs' => [], 'outputs' => [['type' => 'string']]],
            ],
        ]);
    }
}
