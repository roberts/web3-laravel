<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Solana\SolanaProtocolAdapter;
use Tuupola\Base58;

function b58_32(): string
{
    return (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
}

it('submits SPL ApproveChecked and returns a signature', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }

    // Fake RPC calls: resolve ATA, getLatestBlockhash, sendTransaction
    $ataPub = b58_32();
    $blockhash = b58_32();
    Http::fakeSequence()
        ->push([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => [
                    ['pubkey' => $ataPub, 'account' => ['data' => ['program' => 'spl-token', 'parsed' => ['info' => []]]]],
                ],
            ],
            'id' => 1,
        ], 200)
        ->push([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => $blockhash],
            ],
            'id' => 1,
        ], 200)
        ->push([
            'jsonrpc' => '2.0',
            'result' => 'SigApproveChecked11111111111111111111111111111111111',
            'id' => 1,
        ], 200);

    // Chain and wallet setup
    $chain = Blockchain::create([
        'name' => 'Solana Devnet',
        'abbreviation' => 'SOL',
        'chain_id' => 103,
        'rpc' => 'https://api.devnet.solana.com',
        'scanner' => null,
        'supports_eip1559' => false,
        'native_symbol' => 'SOL',
        'native_decimals' => 9,
        'rpc_alternates' => null,
        'is_active' => true,
        'is_default' => true,
        'protocol' => BlockchainProtocol::SOLANA,
    ]);

    $kp = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($kp);
    $public = sodium_crypto_sign_publickey($kp);
    $b58 = new Base58(['characters' => Base58::BITCOIN]);

    $owner = Wallet::create([
        'address' => $b58->encode($public),
        'key' => Crypt::encryptString(bin2hex($secret)),
        'protocol' => BlockchainProtocol::SOLANA,
        'blockchain_id' => $chain->id,
        'is_active' => true,
    ]);

    $mint = b58_32();
    $contract = Contract::factory()->create(['address' => $mint, 'blockchain_id' => $chain->id]);
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 6]);
    $spender = b58_32();

    /** @var SolanaProtocolAdapter $adapter */
    $adapter = app(SolanaProtocolAdapter::class);
    $sig = $adapter->approveToken($token, $owner, $spender, '1000');

    expect($sig)->toBeString()->not->toBe('');
});

it('submits SPL Revoke and returns a signature', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }

    $ataPub = b58_32();
    $blockhash = b58_32();
    Http::fakeSequence()
        ->push([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => [
                    ['pubkey' => $ataPub, 'account' => ['data' => ['program' => 'spl-token', 'parsed' => ['info' => []]]]],
                ],
            ],
            'id' => 1,
        ], 200)
        ->push([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => $blockhash],
            ],
            'id' => 1,
        ], 200)
        ->push([
            'jsonrpc' => '2.0',
            'result' => 'SigRevoke11111111111111111111111111111111111111',
            'id' => 1,
        ], 200);

    $chain = Blockchain::where('protocol', BlockchainProtocol::SOLANA)->first() ?? Blockchain::create([
        'name' => 'Solana Devnet',
        'abbreviation' => 'SOL',
        'chain_id' => 103,
        'rpc' => 'https://api.devnet.solana.com',
        'scanner' => null,
        'supports_eip1559' => false,
        'native_symbol' => 'SOL',
        'native_decimals' => 9,
        'rpc_alternates' => null,
        'is_active' => true,
        'is_default' => true,
        'protocol' => BlockchainProtocol::SOLANA,
    ]);

    $kp = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($kp);
    $public = sodium_crypto_sign_publickey($kp);
    $b58 = new Base58(['characters' => Base58::BITCOIN]);
    $owner = Wallet::create([
        'address' => $b58->encode($public),
        'key' => Crypt::encryptString(bin2hex($secret)),
        'protocol' => BlockchainProtocol::SOLANA,
        'blockchain_id' => $chain->id,
        'is_active' => true,
    ]);

    $mint = b58_32();
    $contract = Contract::factory()->create(['address' => $mint, 'blockchain_id' => $chain->id]);
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 6]);

    /** @var SolanaProtocolAdapter $adapter */
    $adapter = app(SolanaProtocolAdapter::class);
    $sig = $adapter->revokeToken($token, $owner, b58_32());

    expect($sig)->toBeString()->not->toBe('');
});
