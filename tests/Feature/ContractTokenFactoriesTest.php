<?php

use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletNft;

it('creates a contract with optional abi', function () {
    $c1 = Contract::factory()->create();
    expect($c1->id)->toBeInt();
    expect($c1->abi)->toBeNull();

    $c2 = Contract::factory()->withAbi()->create();
    expect($c2->abi)->not->toBeNull();
    expect($c2->abi)->toBeArray();
});

it('creates fungible tokens and nft collections', function () {
    // Test ERC-20 fungible token
    $erc20Token = Token::factory()->withSymbol('DEMO')->withDecimals(6)->create([
        'total_supply' => '1000000000000', // 1M tokens with 6 decimals
    ]);
    expect($erc20Token->symbol)->toBe('DEMO');
    expect($erc20Token->decimals)->toBe(6);
    expect($erc20Token->hasCompleteMetadata())->toBeTrue();

    // Test ERC-721 NFT Collection
    $erc721Collection = NftCollection::factory()->erc721()->create([
        'name' => 'Test NFT Collection',
        'symbol' => 'TNC',
    ]);
    expect($erc721Collection->standard)->toBe(TokenType::ERC721);
    expect($erc721Collection->name)->toBe('Test NFT Collection');
    expect($erc721Collection->symbol)->toBe('TNC');
    expect($erc721Collection->supportsSemiFungible())->toBeFalse();

    // Test ERC-1155 NFT Collection  
    $erc1155Collection = NftCollection::factory()->erc1155()->create([
        'name' => 'Multi Token Collection',
    ]);
    expect($erc1155Collection->standard)->toBe(TokenType::ERC1155);
    expect($erc1155Collection->supportsSemiFungible())->toBeTrue();
    
    // Test NFT ownership
    $wallet = Wallet::factory()->create();
    $nft = WalletNft::factory()->state([
        'wallet_id' => $wallet->id,
        'nft_collection_id' => $erc721Collection->id,
        'token_id' => '1',
        'quantity' => '1',
    ])->create();
    expect($nft->token_id)->toBe('1');
    expect($nft->quantity)->toBe('1');
});
