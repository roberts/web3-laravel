<?php

use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

it('normalizes address and creator to lowercase (EVM)', function () {
    $chain = Blockchain::factory()->create();
    $contract = Contract::factory()->create([
        'blockchain_id' => $chain->id,
        'address' => '0xABCDEF'.str_repeat('0', 34),
        'creator' => '0xABCDEF'.str_repeat('1', 34),
    ]);
    /** @var ProtocolRouter $router */
    $router = app(ProtocolRouter::class);
    $adapter = $router->for($chain->protocol);
    $normalizedAddr = $adapter->normalizeAddress($contract->getRawOriginal('address'));
    $normalizedCreator = $adapter->normalizeAddress($contract->getRawOriginal('creator'));

    expect($contract->address)->toBe($normalizedAddr)
        ->and($contract->creator)->toBe($normalizedCreator);
});

it('casts abi to array', function () {
    $contract = Contract::factory()->create(['abi' => [['type' => 'function', 'name' => 'symbol', 'inputs' => []]]]);
    expect($contract->abi)->toBeArray()->and($contract->abi[0]['name'])->toBe('symbol');
});
