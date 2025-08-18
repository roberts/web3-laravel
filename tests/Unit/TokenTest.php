<?php

use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;

it('casts token fields and relates to contract', function () {
    $contract = Contract::factory()->create();
    $token = Token::factory()->create([
        'contract_id' => $contract->id,
        'symbol' => 'TEST',
        'name' => 'Test Token',
        'decimals' => 18,
        'total_supply' => '1000000000000000000000000', // 1M tokens
    ]);

    expect($token->symbol)->toBe('TEST')
        ->and($token->name)->toBe('Test Token')
        ->and($token->decimals)->toBe(18)
        ->and($token->total_supply)->toBeString()
        ->and($token->contract->id)->toBe($contract->id)
        ->and($token->hasCompleteMetadata())->toBeTrue();
});
