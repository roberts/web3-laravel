<?php

use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;

it('casts token fields and relates to contract', function () {
    $contract = Contract::factory()->create();
    $token = Token::factory()->create([
        'contract_id' => $contract->id,
        'quantity' => '1000000000000000000',
        'token_type' => TokenType::ERC20,
    ]);

    expect($token->quantity)->toBeString()
        ->and($token->token_type)->toBe(TokenType::ERC20)
        ->and($token->contract->id)->toBe($contract->id);
});
