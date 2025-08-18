<?php

use Illuminate\Support\Facades\Auth;
use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Models\KeyRelease;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\KeyReleaseService;

beforeEach(function () {
    $this->keyReleaseService = app(KeyReleaseService::class);
});

it('wallet has key release methods', function () {
    $wallet = Wallet::factory()->custodial()->create([
        'owner_id' => 1,
    ]);

    // Test that the methods exist and return expected types
    expect(method_exists($wallet, 'releasePrivateKey'))->toBeTrue();
    expect(method_exists($wallet, 'canReleaseKey'))->toBeTrue();
    expect(method_exists($wallet, 'getReleaseHistory'))->toBeTrue();
    expect(method_exists($wallet, 'getKeyReleaseCount'))->toBeTrue();
    expect(method_exists($wallet, 'getLastKeyRelease'))->toBeTrue();
});

it('tracks key release count and last release', function () {
    $wallet = Wallet::factory()->custodial()->create([
        'owner_id' => 1,
    ]);

    expect($wallet->getKeyReleaseCount())->toBe(0);
    expect($wallet->getLastKeyRelease())->toBeNull();

    // Create a key release record
    KeyRelease::create([
        'wallet_id' => $wallet->id,
        'user_id' => 1,
        'released_at' => now(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'security_context' => ['test' => true],
    ]);

    expect($wallet->getKeyReleaseCount())->toBe(1);
    expect($wallet->getLastKeyRelease())->not->toBeNull();
});

it('has proper key release relationship', function () {
    $wallet = Wallet::factory()->custodial()->create([
        'owner_id' => 1,
    ]);

    expect($wallet->keyReleases())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    
    // Create some releases
    KeyRelease::create([
        'wallet_id' => $wallet->id,
        'user_id' => 1,
        'released_at' => now(),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'security_context' => [],
    ]);

    expect($wallet->keyReleases()->count())->toBe(1);
});

it('key release service is registered', function () {
    expect(app(KeyReleaseService::class))->toBeInstanceOf(KeyReleaseService::class);
});
