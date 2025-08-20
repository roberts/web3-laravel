<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('enqueues a token creation transaction via wallet model (Solana)', function () {
    $signer = Wallet::factory()->custodial()->create([
        'protocol' => BlockchainProtocol::SOLANA,
        'address' => 'FPc2AJJ8K7a8aL8JxeJv8c2o8qK2pUq7w3M7nQ4o2zP',
    ]);

    $tx = $signer->createFungibleToken([
        'name' => 'TestCoin',
        'symbol' => 'TST',
        'decimals' => 6,
        'initial_supply' => '1000000',
    ]);

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->wallet_id)->toBe($signer->id);
    expect($tx->function_params['operation'] ?? null)->toBe('create_fungible_token');
    expect($tx->meta['standard'] ?? null)->toBe('spl');
    expect($tx->meta['token']['name'] ?? null)->toBe('TestCoin');
});
