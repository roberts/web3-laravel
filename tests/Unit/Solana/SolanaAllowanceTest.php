<?php

use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Core\Provider\Endpoint;
use Roberts\Web3Laravel\Core\Provider\Pool;
use Roberts\Web3Laravel\Core\Rpc\PooledHttpClient;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Protocols\Solana\SolanaJsonRpcClient;
use Roberts\Web3Laravel\Protocols\Solana\SolanaProtocolAdapter;

it('reads allowance (delegated amount) from token accounts', function () {
    Http::fake([
        '*' => Http::response([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => [
                    [
                        'account' => [
                            'data' => [
                                'program' => 'spl-token',
                                'parsed' => [
                                    'info' => [
                                        'delegate' => 'Delegate1111111111111111111111111111111111111',
                                        'delegatedAmount' => [
                                            'amount' => '1234',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'id' => 1,
        ], 200),
    ]);

    $pool = new Pool([new Endpoint('https://api.mainnet-beta.solana.com', 1, [])]);
    $client = new SolanaJsonRpcClient(new PooledHttpClient($pool));

    // minimal token+contract for adapter API
    $contract = Contract::factory()->create(['address' => 'Mint1111111111111111111111111111111111111']);
    $token = Token::factory()->create(['contract_id' => $contract->id]);

    // Build adapter using the real RPC client (HTTP mocked)
    $adapter = app(SolanaProtocolAdapter::class);
    // swap underlying rpc client via container if needed; current binding uses singleton but relies on config default URL

    $amount = $adapter->allowance($token, 'Owner1111111111111111111111111111111111111', 'Delegate1111111111111111111111111111111111111');

    expect($amount)->toBe('1234');
});
