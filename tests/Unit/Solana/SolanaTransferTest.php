<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\Solana\SolanaProtocolAdapter;
use Tuupola\Base58;

it('builds, signs, and sends a native solana transfer', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }

    // Fake RPC: getLatestBlockhash and sendTransaction
    Http::fakeSequence()
        ->push([
            'jsonrpc' => '2.0',
            'result' => [
                'context' => ['slot' => 1],
                'value' => ['blockhash' => (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32))],
            ],
            'id' => 1,
        ], 200)
        ->push([
            'jsonrpc' => '2.0',
            'result' => '5igJ2kTestSignature111111111111111111111111111',
            'id' => 1,
        ], 200);

    // Create a Solana chain and two wallets (from/to)
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

    // Generate ed25519 keypair for sender
    $kp = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($kp);
    $public = sodium_crypto_sign_publickey($kp);
    $b58 = new Base58(['characters' => Base58::BITCOIN]);

    $from = Wallet::create([
        'address' => $b58->encode($public),
        'key' => Crypt::encryptString(bin2hex($secret)),
        'protocol' => BlockchainProtocol::SOLANA,
        'blockchain_id' => $chain->id,
        'is_active' => true,
    ]);

    // Recipient public key
    $toKp = sodium_crypto_sign_keypair();
    $toPub = sodium_crypto_sign_publickey($toKp);
    $toAddress = $b58->encode($toPub);

    /** @var SolanaProtocolAdapter $adapter */
    $adapter = app(SolanaProtocolAdapter::class);

    $sig = $adapter->transferNative($from, $toAddress, '1000');

    expect($sig)->toBeString()->not->toBe('');
});
