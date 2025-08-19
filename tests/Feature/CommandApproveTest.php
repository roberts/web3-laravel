<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Tuupola\Base58;

it('runs web3:token:approve for EVM and creates a Transaction', function () {
    // ERC-20 ABI with approve
    $abi = [
        ['type' => 'function', 'name' => 'approve', 'inputs' => [
            ['type' => 'address', 'name' => 'spender'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
    ];
    $contract = Contract::factory()->withAbi($abi)->create();
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 18]);
    $wallet = Wallet::factory()->create();

    $code = Artisan::call('web3:token:approve', [
        'token' => $token->id,
        'owner' => $wallet->id,
        'spender' => '0x'.str_repeat('a', 40),
        'amount' => '1',
        '--no-interaction' => true,
    ]);

    expect($code)->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Preparing approval')
        ->and($output)->toContain('Approval transaction created');
});

it('runs web3:token:approve for Solana and returns signature', function () {
    if (! extension_loaded('sodium')) {
        $this->markTestSkipped('sodium not available');
    }

    // Create Solana chain
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

    // Setup wallet with ed25519 keys
    $kp = sodium_crypto_sign_keypair();
    $secret = sodium_crypto_sign_secretkey($kp);
    $public = sodium_crypto_sign_publickey($kp);
    $b58 = new Base58(['characters' => Base58::BITCOIN]);
    $wallet = Wallet::create([
        'address' => $b58->encode($public),
        'key' => Crypt::encryptString(bin2hex($secret)),
        'protocol' => BlockchainProtocol::SOLANA,
        'blockchain_id' => $chain->id,
        'is_active' => true,
    ]);

    // Token with SPL mint stored in contract address
    $mint = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $contract = Contract::factory()->create(['address' => $mint, 'blockchain_id' => $chain->id]);
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 6]);

    // Mock RPC: resolve ATA, getLatestBlockhash, sendTransaction
    $ataPub = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $blockhash = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    Http::fakeSequence()
        ->push(['jsonrpc' => '2.0', 'result' => ['context' => ['slot' => 1], 'value' => [['pubkey' => $ataPub]]], 'id' => 1], 200)
        ->push(['jsonrpc' => '2.0', 'result' => ['context' => ['slot' => 1], 'value' => ['blockhash' => $blockhash]], 'id' => 1], 200)
        ->push(['jsonrpc' => '2.0', 'result' => 'SigApproveCmd11111111111111111111111111111111111', 'id' => 1], 200);

    $spender = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $code = Artisan::call('web3:token:approve', [
        'token' => $token->id,
        'owner' => $wallet->id,
        'spender' => $spender,
        'amount' => '5',
        '--no-interaction' => true,
    ]);

    expect($code)->toBe(0);
    $out = Artisan::output();
    expect($out)->toContain('SPL token approve submitted')
        ->and($out)->toContain('Signature:');
});
