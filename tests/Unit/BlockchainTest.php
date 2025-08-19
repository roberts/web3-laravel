<?php

use Roberts\Web3Laravel\Models\Blockchain;

it('creates a blockchain with defaults and casts', function () {
    $chain = Blockchain::factory()->create([
        'chain_id' => 100,
    // Use default or random protocol; avoid asserting a specific chain here
    // 'protocol' => 'evm',
        'supports_eip1559' => false,
        'native_symbol' => 'COIN',
        'native_decimals' => 9,
        'rpc' => 'https://example.org',
        'is_active' => true,
        'is_default' => false,
    ]);

    expect($chain->id)->not()->toBeNull()
    ->and($chain->chain_id)->toBeInt()->toBe(100)
    ->and($chain->protocol)->toBeInstanceOf(\Roberts\Web3Laravel\Enums\BlockchainProtocol::class)
        ->and(is_bool($chain->supports_eip1559))->toBeTrue()
        ->and($chain->native_decimals)->toBeInt()->toBe(9)
        ->and($chain->is_active)->toBeTrue()
        ->and($chain->is_default)->toBeFalse();
});
