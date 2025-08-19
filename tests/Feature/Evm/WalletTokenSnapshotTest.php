<?php

use Illuminate\Support\Facades\Event;
use Roberts\Web3Laravel\Events\WalletTokenAllowanceUpdated;
use Roberts\Web3Laravel\Events\WalletTokenBalanceUpdated;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Services\WalletTokenService;

it('snapshots balances and emits events', function () {
    Event::fake([WalletTokenBalanceUpdated::class]);

    /** @var Contract $contract */
    $contract = Contract::factory()->create([
        'abi' => [[
            'type' => 'function',
            'name' => 'balanceOf',
            'inputs' => [['name' => 'owner', 'type' => 'address']],
            'outputs' => [['type' => 'uint256']],
        ]],
    ]);

    /** @var Token $token */
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 18]);

    /** @var Wallet $w1 */
    $w1 = Wallet::factory()->create(['address' => '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);

    // Mock ContractCaller response for balanceOf
    $caller = app(ContractCaller::class);
    $mock = \Mockery::mock($caller)->makePartial();
    $mock->shouldReceive('call')->andReturnUsing(function ($abi, $addr, $fn, $params) {
        return ['1000000000000000000']; // 1 token
    });
    app()->instance(ContractCaller::class, $mock);

    $service = app(WalletTokenService::class);
    $rows = $service->snapshotBalances($token, [$w1->address]);
    expect($rows)->toHaveCount(1);

    Event::assertDispatched(WalletTokenBalanceUpdated::class);
});

it('snapshots allowances and emits event on change', function () {
    Event::fake([WalletTokenAllowanceUpdated::class]);

    /** @var Contract $contract */
    $contract = Contract::factory()->create([
        'abi' => [[
            'type' => 'function',
            'name' => 'allowance',
            'inputs' => [['type' => 'address'], ['type' => 'address']],
            'outputs' => [['type' => 'uint256']],
        ]],
    ]);

    /** @var Token $token */
    $token = Token::factory()->create(['contract_id' => $contract->id]);

    /** @var Wallet $owner */
    $owner = Wallet::factory()->create(['address' => '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb']);

    $caller = app(ContractCaller::class);
    $mock = \Mockery::mock($caller)->makePartial();
    $mock->shouldReceive('call')->andReturnUsing(function ($abi, $addr, $fn, $params) {
        return ['42'];
    });
    app()->instance(ContractCaller::class, $mock);

    $service = app(WalletTokenService::class);
    $allowance = $service->snapshotAllowance($token, $owner, '0xcccccccccccccccccccccccccccccccccccccccc');
    expect($allowance)->toBe('42');

    Event::assertDispatched(WalletTokenAllowanceUpdated::class);
});
