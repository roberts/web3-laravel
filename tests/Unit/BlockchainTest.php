<?php

use Roberts\Web3Laravel\Models\Blockchain;

it('creates a blockchain with defaults and casts', function () {
    $chain = Blockchain::factory()->create([
        'chain_id' => 8453,
        'evm' => true,
        'supports_eip1559' => true,
        'native_symbol' => 'ETH',
        'native_decimals' => 18,
        'rpc' => 'https://example.org',
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($chain->id)->not()->toBeNull()
        ->and($chain->chain_id)->toBeInt()->toBe(8453)
        ->and($chain->evm)->toBeTrue()
        ->and($chain->supports_eip1559)->toBeTrue()
        ->and($chain->native_decimals)->toBeInt()->toBe(18)
        ->and($chain->is_active)->toBeTrue()
        ->and($chain->is_default)->toBeFalse();
});
