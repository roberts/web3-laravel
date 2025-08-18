<?php

use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Models\Wallet;

it('can create wallets with different types', function () {
    $custodial = Wallet::factory()->custodial()->create();
    $shared = Wallet::factory()->shared()->create();
    $external = Wallet::factory()->external()->create();

    expect($custodial->wallet_type)->toBe(WalletType::CUSTODIAL);
    expect($shared->wallet_type)->toBe(WalletType::SHARED);
    expect($external->wallet_type)->toBe(WalletType::EXTERNAL);
});

it('validates private key storage based on wallet type', function () {
    $custodial = Wallet::factory()->custodial()->create();
    $shared = Wallet::factory()->shared()->create();
    $external = Wallet::factory()->external()->create();

    expect($custodial->canStorePrivateKey())->toBeTrue();
    expect($shared->canStorePrivateKey())->toBeTrue();
    expect($external->canStorePrivateKey())->toBeFalse();

    expect($custodial->requiresExternalSigning())->toBeFalse();
    expect($shared->requiresExternalSigning())->toBeFalse();
    expect($external->requiresExternalSigning())->toBeTrue();
});

it('has proper wallet type helper methods', function () {
    $custodial = Wallet::factory()->custodial()->create();
    $shared = Wallet::factory()->shared()->create();
    $external = Wallet::factory()->external()->create();

    expect($custodial->isCustodial())->toBeTrue();
    expect($custodial->isShared())->toBeFalse();
    expect($custodial->isExternal())->toBeFalse();

    expect($shared->isCustodial())->toBeFalse();
    expect($shared->isShared())->toBeTrue();
    expect($shared->isExternal())->toBeFalse();

    expect($external->isCustodial())->toBeFalse();
    expect($external->isShared())->toBeFalse();
    expect($external->isExternal())->toBeTrue();
});

it('provides descriptive labels and descriptions', function () {
    $custodial = Wallet::factory()->custodial()->create();

    expect($custodial->getTypeLabel())->toBe('Custodial');
    expect($custodial->getTypeDescription())->toContain('Fully managed wallet');
});

it('can scope wallets by type', function () {
    Wallet::factory()->custodial()->count(3)->create();
    Wallet::factory()->shared()->count(2)->create();
    Wallet::factory()->external()->count(1)->create();

    expect(Wallet::ofType(WalletType::CUSTODIAL)->count())->toBe(3);
    expect(Wallet::ofType(WalletType::SHARED)->count())->toBe(2);
    expect(Wallet::ofType(WalletType::EXTERNAL)->count())->toBe(1);
});

it('throws exception when setting private key on external wallet', function () {
    $external = Wallet::factory()->external()->create();

    expect(fn () => $external->setPrivateKey('0x123'))->toThrow(InvalidArgumentException::class);
});
