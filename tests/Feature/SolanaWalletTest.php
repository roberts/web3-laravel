<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Services\WalletService;
use Tuupola\Base58;

it('creates a solana wallet with base58 address and encrypted key', function () {
    // Ensure a solana blockchain exists
    $chain = Blockchain::create([
        'name' => 'Solana Mainnet',
        'abbreviation' => 'SOL',
        'chain_id' => 101, // Solana network id placeholder
        'rpc' => config('web3-laravel.solana.default_rpc'),
        'scanner' => null,
        'supports_eip1559' => false,
        'native_symbol' => 'SOL',
        'native_decimals' => 9,
        'rpc_alternates' => null,
        'is_active' => true,
        'is_default' => true,
        'protocol' => BlockchainProtocol::SOLANA,
    ]);

    $wallet = app(WalletService::class)->createForBlockchain($chain);

    expect($wallet->address)->not->toBeEmpty();

    // Validate base58 and decoded length 32
    $decoded = (new Base58(['characters' => Base58::BITCOIN]))->decode($wallet->address);
    expect(strlen($decoded))->toBe(32);

    // Key is encrypted and not empty
    expect($wallet->key)->not->toBeEmpty();
});
