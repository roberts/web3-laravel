<?php

use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletNft;
use Roberts\Web3Laravel\Enums\TokenType;

it('can create and use fungible token model', function () {
    $token = Token::factory()->create([
        'symbol' => 'TEST',
        'name' => 'Test Token',
        'decimals' => 18,
        'total_supply' => '1000000000000000000000000', // 1M tokens
    ]);

    expect($token->symbol)->toBe('TEST');
    expect($token->name)->toBe('Test Token');
    expect($token->decimals)->toBe(18);
    expect($token->getDisplayName())->toBe('Test Token');
    expect($token->hasCompleteMetadata())->toBeTrue();
    
    // Test amount formatting
    expect($token->formatAmount('1000000000000000000'))->toBe('1');
    expect($token->parseAmount('1.5'))->toBe('1500000000000000000');
});

it('can create and use nft collection model', function () {
    $collection = NftCollection::factory()->erc721()->create([
        'name' => 'Test Collection',
        'symbol' => 'TC',
        'standard' => TokenType::ERC721,
    ]);

    expect($collection->name)->toBe('Test Collection');
    expect($collection->symbol)->toBe('TC');
    expect($collection->standard)->toBe(TokenType::ERC721);
    expect($collection->supportsSemiFungible())->toBeFalse();
    expect($collection->getOwnerCount())->toBe(0);
    expect($collection->getUniqueTokenCount())->toBe(0);
});

it('can create nft ownership records', function () {
    $wallet = Wallet::factory()->create();
    $collection = NftCollection::factory()->erc721()->create();
    
    $nft = WalletNft::factory()
        ->for($wallet)
        ->for($collection)
        ->withTokenId('123')
        ->state(['quantity' => '1'])
        ->create();

    expect($nft->wallet_id)->toBe($wallet->id);
    expect($nft->nft_collection_id)->toBe($collection->id);
    expect($nft->token_id)->toBe('123');
    expect($nft->quantity)->toBe('1');
    expect($nft->getDisplayName())->toContain('#123');
    expect($nft->isSemiFungible())->toBeFalse();
});

it('handles erc1155 semi-fungible tokens', function () {
    $collection = NftCollection::factory()->erc1155()->create();
    $wallet = Wallet::factory()->create();
    
    $nft = WalletNft::factory()->erc1155()->create([
        'wallet_id' => $wallet->id,
        'nft_collection_id' => $collection->id,
        'token_id' => '456',
        'quantity' => '50',
    ]);

    expect($collection->standard)->toBe(TokenType::ERC1155);
    expect($collection->supportsSemiFungible())->toBeTrue();
    expect($nft->quantity)->toBe('50');
    expect($nft->isSemiFungible())->toBeTrue();
    expect($nft->canTransferQuantity('25'))->toBeTrue();
    expect($nft->canTransferQuantity('75'))->toBeFalse();
});

it('wallet has nft relationships and helper methods', function () {
    $wallet = Wallet::factory()->create();
    $collection1 = NftCollection::factory()->create();
    $collection2 = NftCollection::factory()->create();
    
    // Create NFTs with specific token IDs
    WalletNft::factory()
        ->for($wallet)
        ->for($collection1)
        ->withTokenId('123')
        ->create();

    WalletNft::factory()
        ->for($wallet)
        ->for($collection1)
        ->withTokenId('456')
        ->create();

    WalletNft::factory()
        ->for($wallet)
        ->for($collection2)
        ->withTokenId('789')
        ->create();

    // Test NFT ownership records
    $nfts = $wallet->nfts()->get();
    expect($nfts)->toHaveCount(3)
        ->and($nfts->where('token_id', '123')->first()->getDisplayName())->toContain('#123')
        ->and($nfts->where('token_id', '456')->first()->getDisplayName())->toContain('#456')
        ->and($nfts->where('token_id', '789')->first()->getDisplayName())->toContain('#789');

    expect($wallet->getNftCount())->toBe(3);
    expect($wallet->getUniqueCollectionCount())->toBe(2);
    expect($wallet->ownsNft($collection1, '123'))->toBeTrue();
    expect($wallet->ownsNft($collection1, '999'))->toBeFalse();
    
    $gallery = $wallet->getNftGallery();
    expect($gallery)->toHaveCount(3);
});

it('nft collection has analytics methods', function () {
    $collection = NftCollection::factory()->create();
    $wallet1 = Wallet::factory()->create();
    $wallet2 = Wallet::factory()->create();
    
    // Wallet1 owns 2 NFTs
    WalletNft::factory()->create([
        'wallet_id' => $wallet1->id,
        'nft_collection_id' => $collection->id,
        'token_id' => '1',
        'rarity_rank' => 1,
    ]);
    
    WalletNft::factory()->create([
        'wallet_id' => $wallet1->id,
        'nft_collection_id' => $collection->id,
        'token_id' => '2',
        'rarity_rank' => 2,
    ]);
    
    // Wallet2 owns 1 NFT
    WalletNft::factory()->create([
        'wallet_id' => $wallet2->id,
        'nft_collection_id' => $collection->id,
        'token_id' => '3',
        'rarity_rank' => 3,
    ]);

    expect($collection->getOwnerCount())->toBe(2);
    expect($collection->getUniqueTokenCount())->toBe(3);
    
    $ownerDistribution = $collection->getOwnerDistribution();
    expect($ownerDistribution[$wallet1->id])->toBe(2);
    expect($ownerDistribution[$wallet2->id])->toBe(1);
    
    $rarityRanking = $collection->getRarityRanking();
    expect($rarityRanking)->toHaveCount(3);
    expect($rarityRanking->first()->rarity_rank)->toBe(1);
});
