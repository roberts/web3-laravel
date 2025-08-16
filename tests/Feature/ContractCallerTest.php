<?php

use Roberts\Web3Laravel\Services\ContractCaller;

it('builds call data and decodes outputs using ABI helpers', function () {
    $abi = [
        [
            'type' => 'function',
            'name' => 'balanceOf',
            'inputs' => [['name' => 'owner', 'type' => 'address']],
            'outputs' => [['name' => '', 'type' => 'uint256']],
            'stateMutability' => 'view'
        ],
    ];

    $svc = app(ContractCaller::class);

    $data = $svc->encodeCallData($abi, 'balanceOf', ['0x000000000000000000000000000000000000dEaD']);
    // Function selector 0x70a08231 + padded address
    expect(substr($data, 0, 10))->toBe('0x70a08231');

    // Simulate a return value of 1 as 32-byte hex
    $raw = '0x' . str_pad('1', 64, '0', STR_PAD_LEFT);
    $decoded = $svc->decodeCallResult($abi, 'balanceOf', $raw);
    expect($decoded[0])->toBe('1');
});
