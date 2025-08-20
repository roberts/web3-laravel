<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

it('enqueues a token creation transaction via wallet model (Sui)', function () {
    $signer = Wallet::factory()->custodial()->create([
        'protocol' => BlockchainProtocol::SUI,
        'address' => '0x'.str_repeat('a', 64),
    ]);

    $tx = $signer->createFungibleToken([
        'name' => 'SuiCoin',
        'symbol' => 'SUIX',
        'decimals' => 9,
        'initial_supply' => '1000000000',
    ]);

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->wallet_id)->toBe($signer->id);
    expect($tx->function_params['operation'] ?? null)->toBe('create_fungible_token');
    expect($tx->meta['standard'] ?? null)->toBe('sui');
});

it('enqueues a token creation transaction via wallet model (Hedera)', function () {
    $signer = Wallet::factory()->custodial()->create([
        'protocol' => BlockchainProtocol::HEDERA,
        'address' => '0.0.1234',
    ]);

    $tx = $signer->createFungibleToken([
        'name' => 'HederaCoin',
        'symbol' => 'HEDX',
        'decimals' => 8,
        'initial_supply' => '4200000000',
    ]);

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->wallet_id)->toBe($signer->id);
    expect($tx->function_params['operation'] ?? null)->toBe('create_fungible_token');
    expect($tx->meta['standard'] ?? null)->toBe('hts');
});

it('enqueues a token creation transaction via wallet model (Cardano)', function () {
    $signer = Wallet::factory()->custodial()->create([
        'protocol' => BlockchainProtocol::CARDANO,
        'address' => 'addr1q'.str_repeat('z', 20),
    ]);

    $tx = $signer->createFungibleToken([
        'name' => 'AdaCoin',
        'symbol' => 'ADAX',
        'decimals' => 6,
        'initial_supply' => '1000000',
    ]);

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->wallet_id)->toBe($signer->id);
    expect($tx->function_params['operation'] ?? null)->toBe('create_fungible_token');
    expect($tx->meta['standard'] ?? null)->toBe('cardano');
});
