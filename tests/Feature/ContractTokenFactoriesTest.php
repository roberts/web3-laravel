<?php

use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;

it('creates a contract with optional abi', function () {
    $c1 = Contract::factory()->create();
    expect($c1->id)->toBeInt();
    expect($c1->abi)->toBeNull();

    $c2 = Contract::factory()->withAbi()->create();
    expect($c2->abi)->not->toBeNull();
    expect($c2->abi)->toBeArray();
});

it('creates tokens of different types', function () {
    $erc20 = Token::factory()->erc20('DEMO', 6)->create(['quantity' => '1000000']);
    expect($erc20->token_type)->toBe(TokenType::ERC20);
    expect($erc20->symbol)->toBe('DEMO');
    expect($erc20->decimals)->toBe(6);
    expect($erc20->token_id)->toBeNull();

    $erc721 = Token::factory()->erc721('1')->create();
    expect($erc721->token_type)->toBe(TokenType::ERC721);
    expect($erc721->token_id)->toBe('1');
    expect($erc721->symbol)->toBeNull();

    $erc1155 = Token::factory()->erc1155('10', '250')->create();
    expect($erc1155->token_type)->toBe(TokenType::ERC1155);
    expect($erc1155->token_id)->toBe('10');
    expect($erc1155->quantity)->toBe('250');
});
