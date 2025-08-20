<?php

use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Models\Wallet;

dataset('protocols', [
    'evm' => [BlockchainProtocol::EVM],
    'solana' => [BlockchainProtocol::SOLANA],
    'bitcoin' => [BlockchainProtocol::BITCOIN],
    'sui' => [BlockchainProtocol::SUI],
    'xrpl' => [BlockchainProtocol::XRPL],
    'cardano' => [BlockchainProtocol::CARDANO],
    'hedera' => [BlockchainProtocol::HEDERA],
    'ton' => [BlockchainProtocol::TON],
]);

it('encrypts and decrypts private key transparently across protocols', function (BlockchainProtocol $protocol) {
    $plain = '0x'.strtolower(bin2hex(random_bytes(32)));
    $wallet = Wallet::factory()->create([
        'key' => $plain,
        'protocol' => $protocol,
    ]);

    // Stored encrypted value should not equal plain
    expect($wallet->getAttributes()['key'])->not()->toBe($plain);

    // Decrypt helper returns original
    expect($wallet->decryptKey())->toBe($plain);

    // Masked key includes start and end fragments only
    $masked = $wallet->maskedKey();
    expect($masked)->toContain(substr($plain, 0, 6))
        ->and($masked)->toContain(substr($plain, -4));
})->with('protocols');
