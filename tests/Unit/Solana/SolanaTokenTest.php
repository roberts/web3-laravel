<?php

use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Core\Provider\Endpoint;
use Roberts\Web3Laravel\Core\Provider\Pool;
use Roberts\Web3Laravel\Core\Rpc\PooledHttpClient;
use Roberts\Web3Laravel\Protocols\Solana\SolanaJsonRpcClient;
use Roberts\Web3Laravel\Protocols\Solana\SplToken;

it('parses and sums token balances from getTokenAccountsByOwner', function () {
    // Fake a Solana RPC response with jsonParsed token accounts
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
                                        'mint' => 'Mint1111111111111111111111111111111111111',
                                        'tokenAmount' => [
                                            'amount' => '100',
                                            'decimals' => 6,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'account' => [
                            'data' => [
                                'program' => 'spl-token',
                                'parsed' => [
                                    'info' => [
                                        'mint' => 'Mint1111111111111111111111111111111111111',
                                        'tokenAmount' => [
                                            'amount' => '250',
                                            'decimals' => 6,
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

    $pool = new Pool([ new Endpoint('https://api.mainnet-beta.solana.com', 1, []) ]);
    $client = new SolanaJsonRpcClient(new PooledHttpClient($pool));

    $resp = $client->getTokenAccountsByOwner('Owner1111111111111111111111111111111111111', ['programId' => SplToken::TOKEN_PROGRAM_ID]);
    $sum = SplToken::sumParsedTokenBalance($resp);

    expect($sum)->toBe('350');
});
