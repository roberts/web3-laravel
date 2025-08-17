<?php

use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;

it('normalizes address and creator to lowercase', function () {
    $chain = Blockchain::factory()->create();
    $contract = Contract::factory()->create([
        'blockchain_id' => $chain->id,
        'address' => '0xABCDEF'.str_repeat('0', 34),
        'creator' => '0xABCDEF'.str_repeat('1', 34),
    ]);

    expect($contract->address)->toBe(strtolower($contract->address))
        ->and($contract->creator)->toBe(strtolower($contract->creator));
});

it('casts abi to array', function () {
    $contract = Contract::factory()->create(['abi' => [['type' => 'function', 'name' => 'symbol', 'inputs' => []]]]);
    expect($contract->abi)->toBeArray()->and($contract->abi[0]['name'])->toBe('symbol');
});
