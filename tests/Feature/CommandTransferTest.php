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

it('runs web3:token:transfer for EVM and queues a Transaction', function () {
    // Minimal ERC-20 ABI used by TokenService to encode transfer
    $abi = [
        ['type' => 'function', 'name' => 'transfer', 'inputs' => [
            ['type' => 'address', 'name' => 'to'],
            ['type' => 'uint256', 'name' => 'amount'],
        ], 'outputs' => [['type' => 'bool']]],
    ];
    $contract = Contract::factory()->withAbi($abi)->create();
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 18]);
    $from = Wallet::factory()->create();

    $code = Artisan::call('web3:token:transfer', [
        'token' => $token->id,
        'from' => $from->id,
        'to' => '0x'.str_repeat('b', 40),
        'amount' => '1',
        '--no-interaction' => true,
    ]);

    expect($code)->toBe(0);
    $out = Artisan::output();
    expect($out)
        ->toContain('Preparing transfer')
        ->and($out)->toContain('Transfer queued for processing')
        ->and($out)->toContain('Transaction ID:');
});

it('runs web3:token:transfer for Solana and returns signature', function () {
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

    // Wallet with ed25519 key
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

    // Token: use contract address as SPL mint
    $mint = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $contract = Contract::factory()->create(['address' => $mint, 'blockchain_id' => $chain->id]);
    $token = Token::factory()->create(['contract_id' => $contract->id, 'decimals' => 6]);

    // Mock RPC calls: owner ATA, recipient ATA, latest blockhash, sendTransaction
    $ownerAta = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $toAta = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $blockhash = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    Http::fakeSequence()
        ->push(['jsonrpc' => '2.0', 'result' => ['context' => ['slot' => 1], 'value' => [['pubkey' => $ownerAta]]], 'id' => 1], 200)
        ->push(['jsonrpc' => '2.0', 'result' => ['context' => ['slot' => 1], 'value' => [['pubkey' => $toAta]]], 'id' => 1], 200)
        ->push(['jsonrpc' => '2.0', 'result' => ['context' => ['slot' => 1], 'value' => ['blockhash' => $blockhash]], 'id' => 1], 200)
        ->push(['jsonrpc' => '2.0', 'result' => 'SigTransferCmd1111111111111111111111111111111111', 'id' => 1], 200);

    $recipient = (new Base58(['characters' => Base58::BITCOIN]))->encode(random_bytes(32));
    $code = Artisan::call('web3:token:transfer', [
        'token' => $token->id,
        'from' => $wallet->id,
        'to' => $recipient,
        'amount' => '5',
        '--no-interaction' => true,
    ]);

    expect($code)->toBe(0);
    $out = Artisan::output();
    expect($out)
        ->toContain('SPL token transfer submitted')
        ->and($out)->toContain('Signature:');
});
