<?php

use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\TokenService;

it('creates a transaction for erc20 transfer', function () {
    $erc20Abi = [
        ['type' => 'function', 'name' => 'transfer', 'inputs' => [
            ['type' => 'address', 'name' => 'to'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
        ['type' => 'function', 'name' => 'approve', 'inputs' => [
            ['type' => 'address', 'name' => 'spender'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [
            ['type' => 'address', 'name' => 'owner'],
        ], 'outputs' => [['type' => 'uint256']]],
    ];
    $contract = Contract::factory()->withAbi($erc20Abi)->create();
    $token = Token::factory()->create(['contract_id' => $contract->id]);
    $from = Wallet::factory()->create();

    $svc = app(TokenService::class);
    $tx = $svc->transfer($token, $from, '0x'.str_repeat('1', 40), '1000');

    expect($tx->wallet_id)->toBe($from->id)
        ->and($tx->contract_id)->toBe($contract->id)
        ->and($tx->to)->toBe($contract->address)
        ->and($tx->meta['token_operation'])->toBe('transfer')
        ->and($tx->meta['recipient'])->toBe('0x'.str_repeat('1', 40));
});

it('creates a transaction for erc20 approve', function () {
    $erc20Abi = [
        ['type' => 'function', 'name' => 'transfer', 'inputs' => [
            ['type' => 'address', 'name' => 'to'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
        ['type' => 'function', 'name' => 'approve', 'inputs' => [
            ['type' => 'address', 'name' => 'spender'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
        ['type' => 'function', 'name' => 'balanceOf', 'inputs' => [
            ['type' => 'address', 'name' => 'owner'],
        ], 'outputs' => [['type' => 'uint256']]],
    ];
    $contract = Contract::factory()->withAbi($erc20Abi)->create();
    $token = Token::factory()->create(['contract_id' => $contract->id]);
    $owner = Wallet::factory()->create();

    $svc = app(TokenService::class);
    $tx = $svc->approve($token, $owner, '0x'.str_repeat('2', 40), '500');

    expect($tx->wallet_id)->toBe($owner->id)
        ->and($tx->contract_id)->toBe($contract->id)
        ->and($tx->to)->toBe($contract->address)
        ->and($tx->meta['token_operation'])->toBe('approve')
        ->and($tx->meta['spender'])->toBe('0x'.str_repeat('2', 40))
        ->and($tx->meta['amount'])->toBe('500');
});
