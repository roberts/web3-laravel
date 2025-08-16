<?php

use Roberts\Web3Laravel\Services\ContractCaller;

it('builds call data and decodes outputs using ABI helpers', function () {
    $abi = [
        [
            'type' => 'function',
            'name' => 'balanceOf',
            'inputs' => [['name' => 'owner', 'type' => 'address']],
            'outputs' => [['name' => '', 'type' => 'uint256']],
            'stateMutability' => 'view',
        ],
    ];

    $svc = app(ContractCaller::class);

    $data = $svc->encodeCallData($abi, 'balanceOf', ['0x000000000000000000000000000000000000dEaD']);
    // Function selector 0x70a08231 + padded address
    expect(substr($data, 0, 10))->toBe('0x70a08231');

    // Simulate a return value of 1 as 32-byte hex
    $raw = '0x'.str_pad('1', 64, '0', STR_PAD_LEFT);
    $decoded = $svc->decodeCallResult($abi, 'balanceOf', $raw);
    expect($decoded[0])->toBe('1');
});

it('decodes tuple and array outputs correctly', function () {
    $abi = [
        [
            'type' => 'function',
            'name' => 'info',
            'inputs' => [],
            'outputs' => [[
                'name' => 'pair',
                'type' => 'tuple',
                'components' => [
                    ['name' => 'a', 'type' => 'uint256'],
                    ['name' => 'b', 'type' => 'address'],
                ],
            ]],
            'stateMutability' => 'view',
        ],
        [
            'type' => 'function',
            'name' => 'values',
            'inputs' => [],
            'outputs' => [[
                'name' => 'arr',
                'type' => 'uint256[]',
            ]],
            'stateMutability' => 'view',
        ],
    ];

    $svc = app(\Roberts\Web3Laravel\Services\ContractCaller::class);

    // For tuple (uint256,address), craft a minimal ABI-encoded return:
    // decodeParameters returns an array of values; for a single tuple it returns as a single element that is a list
    // We simulate: pair = (1, 0x000...0002)
    $tupleEncoded = '0x'
        // offset to tuple data (if dynamic) is not needed for simple single tuple in outputs with web3p Ethabi; encode as plain
        . str_pad('1', 64, '0', STR_PAD_LEFT) // a = 1
        . str_pad(substr(strtolower('0x0000000000000000000000000000000000000002'), 2), 64, '0', STR_PAD_LEFT); // b
    $decodedTuple = $svc->decodeCallResult($abi, 'info', $tupleEncoded);
    // Depending on decoder behavior, the tuple may be nested; normalize either way
    $pair = is_array($decodedTuple[0] ?? null) ? $decodedTuple[0] : $decodedTuple;
    expect((string) ($pair[0] ?? ''))
        ->toBe('1');

    // For uint256[] = [1,2], proper dynamic return encoding is:
    // head: offset (0x20), then tail: length, elements...
    $arrayEncoded = '0x'
        . str_pad(dechex(32), 64, '0', STR_PAD_LEFT) // offset = 0x20
        . str_pad(dechex(2), 64, '0', STR_PAD_LEFT)  // length = 2
        . str_pad(dechex(1), 64, '0', STR_PAD_LEFT)  // value[0]
        . str_pad(dechex(2), 64, '0', STR_PAD_LEFT); // value[1]
    $decodedArr = $svc->decodeCallResult($abi, 'values', $arrayEncoded);
    $arr = $decodedArr[0] ?? [];
    expect((string) ($arr[0] ?? ''))->toBe('1');
    expect((string) ($arr[1] ?? ''))->toBe('2');
});
