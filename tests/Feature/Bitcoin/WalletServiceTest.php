<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\WalletService;

it('creates a bitcoin wallet with bech32 address and encrypted key', function () {
    $chain = Blockchain::factory()->create([
        'protocol' => BlockchainProtocol::BITCOIN,
        'native_symbol' => 'BTC',
        'native_decimals' => 8,
    ]);
    $service = new WalletService;

    $wallet = $service->create(['network' => 'testnet'], null, $chain);

    expect($wallet)->toBeInstanceOf(Wallet::class);
    expect($wallet->protocol->value)->toBe('bitcoin');
    expect($wallet->address)->toStartWith('tb1');

    $plain = $wallet->decryptKey();
    expect($plain)->not->toBeNull();
});
