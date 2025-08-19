<?php

use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\ContractCaller;
use Roberts\Web3Laravel\Services\TokenService;

it('returns allowance for erc20 via ContractCaller', function () {
    $erc20Abi = [
        ['type' => 'function', 'name' => 'allowance', 'inputs' => [
            ['type' => 'address', 'name' => 'owner'],
            ['type' => 'address', 'name' => 'spender'],
        ], 'outputs' => [['type' => 'uint256']]],
    ];
    $contract = Contract::factory()->withAbi($erc20Abi)->create();
    $token = Token::factory()->create(['contract_id' => $contract->id]);

    // Bind a fake ContractCaller that returns a fixed allowance
    app()->bind(ContractCaller::class, function () {
        return new class(app(\Roberts\Web3Laravel\Protocols\Evm\EvmClientInterface::class)) extends ContractCaller {
            public function __construct($evm) { parent::__construct($evm); }
            public function call(array $abi, string $to, string $function, array $params = [], ?string $from = null, string $blockTag = 'latest'): array
            {
                return ['777'];
            }
        };
    });

    $svc = app(TokenService::class);
    $val = $svc->allowance($token, '0x'.str_repeat('1', 40), '0x'.str_repeat('2', 40));

    expect($val)->toBe('777');
});
