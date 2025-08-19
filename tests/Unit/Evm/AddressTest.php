<?php

use Roberts\Web3Laravel\Support\Address;

it('computes EIP-55 checksum correctly', function () {
    // Sample from EIP-55
    $lower = '0x52908400098527886e0f7030069857d2e4169ee7';
    $checksum = Address::toChecksum($lower);
    expect($checksum)->toBe('0x52908400098527886E0F7030069857D2E4169EE7');
});

it('validates evm address with or without checksum', function () {
    expect(Address::isValidEvm('0x52908400098527886e0f7030069857d2e4169ee7'))->toBeTrue();
    expect(Address::isValidEvm('0x52908400098527886E0F7030069857D2E4169EE7'))->toBeTrue();
    expect(Address::isValidEvm('0x52908400098527886E0F7030069857D2E4169EE7', true))->toBeTrue();
    expect(Address::isValidEvm('0x52908400098527886E0F7030069857D2E4169ee7', true))->toBeFalse();
});

it('normalizes and compares addresses case-insensitively', function () {
    $a = '0xDeaDbeefdEAdbeefdEadbEEFdeadbeEFdEAdBeeF';
    $b = '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';
    expect(Address::normalize($a))->toBe('0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef');
    expect(Address::equals($a, $b))->toBeTrue();
});
