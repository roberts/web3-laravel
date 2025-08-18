<?php

it('has correct token model structure', function () {
    // Test that the Token model has the expected properties and methods
    $reflection = new ReflectionClass(\Roberts\Web3Laravel\Models\Token::class);

    expect($reflection->hasMethod('getDisplayName'))->toBeTrue();
    expect($reflection->hasMethod('formatAmount'))->toBeTrue();
    expect($reflection->hasMethod('parseAmount'))->toBeTrue();
    expect($reflection->hasMethod('hasCompleteMetadata'))->toBeTrue();
});

it('has correct nft collection model structure', function () {
    $reflection = new ReflectionClass(\Roberts\Web3Laravel\Models\NftCollection::class);

    expect($reflection->hasMethod('getOwnerCount'))->toBeTrue();
    expect($reflection->hasMethod('getUniqueTokenCount'))->toBeTrue();
    expect($reflection->hasMethod('supportsSemiFungible'))->toBeTrue();
});

it('has correct wallet nft model structure', function () {
    $reflection = new ReflectionClass(\Roberts\Web3Laravel\Models\WalletNft::class);

    expect($reflection->hasMethod('getDisplayName'))->toBeTrue();
    expect($reflection->hasMethod('isSemiFungible'))->toBeTrue();
    expect($reflection->hasMethod('canTransferQuantity'))->toBeTrue();
});

it('has correct nft standard enum', function () {
    expect(\Roberts\Web3Laravel\Enums\TokenType::ERC721->isSemiFungible())->toBeFalse();
    expect(\Roberts\Web3Laravel\Enums\TokenType::ERC1155->isSemiFungible())->toBeTrue();
    expect(\Roberts\Web3Laravel\Enums\TokenType::ERC721->supportsQuantity())->toBeFalse();
    expect(\Roberts\Web3Laravel\Enums\TokenType::ERC1155->supportsQuantity())->toBeTrue();
});

it('token format and parse amounts correctly', function () {
    // Create a mock token instance for testing
    $token = new \Roberts\Web3Laravel\Models\Token;
    $token->decimals = 18;

    // Test formatting
    expect($token->formatAmount('1000000000000000000'))->toBe('1');
    expect($token->formatAmount('1500000000000000000'))->toBe('1.5');
    expect($token->formatAmount('100000000000000000'))->toBe('0.1'); // Fixed expectation

    // Test parsing
    expect($token->parseAmount('1'))->toBe('1000000000000000000');
    expect($token->parseAmount('1.5'))->toBe('1500000000000000000');
    expect($token->parseAmount('0.1'))->toBe('100000000000000000'); // Fixed expectation
});
