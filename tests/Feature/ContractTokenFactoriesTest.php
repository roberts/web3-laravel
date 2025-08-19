<?php

use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\NftCollection;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Models\WalletNft;

it('creates a contract with optional abi (protocol-agnostic)', function () {
    $c1 = Contract::factory()->create();
    expect($c1->id)->toBeInt();
    expect($c1->abi)->toBeNull();

    $c2 = Contract::factory()->withAbi()->create();
    expect($c2->abi)->not->toBeNull();
    expect($c2->abi)->toBeArray();
});

it('creates token and nft models without protocol assumptions', function () {
    $token = Token::factory()->withSymbol('DEMO')->withDecimals(6)->create([
        'total_supply' => '1000000000000',
    ]);
    expect($token->symbol)->toBe('DEMO');
    expect($token->decimals)->toBe(6);
    expect($token->hasCompleteMetadata())->toBeTrue();

    $erc721 = NftCollection::factory()->erc721()->create([
        'name' => 'Test NFT Collection',
        'symbol' => 'TNC',
    ]);
    expect($erc721->standard)->toBe(TokenType::ERC721);
    expect($erc721->supportsSemiFungible())->toBeFalse();

    $erc1155 = NftCollection::factory()->erc1155()->create([
        'name' => 'Multi Token Collection',
    ]);
    expect($erc1155->standard)->toBe(TokenType::ERC1155);
    expect($erc1155->supportsSemiFungible())->toBeTrue();

    $wallet = Wallet::factory()->create();
    $nft = WalletNft::factory()->state([
        'wallet_id' => $wallet->id,
        'nft_collection_id' => $erc721->id,
        'token_id' => '1',
        'quantity' => '1',
    ])->create();
    expect($nft->token_id)->toBe('1');
});
