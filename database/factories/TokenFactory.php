<?php

namespace Roberts\Web3Laravel\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Roberts\Web3Laravel\Enums\TokenType;
use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;

/**
 * @extends Factory<Token>
 */
class TokenFactory extends Factory
{
    protected $model = Token::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'token_type' => TokenType::ERC20,
            'quantity' => '0',
            'token_id' => null,
            'symbol' => null,
            'decimals' => null,
        ];
    }

    public function erc20(string $symbol = 'TKN', int $decimals = 18): self
    {
        return $this->state([
            'token_type' => TokenType::ERC20,
            'symbol' => $symbol,
            'decimals' => $decimals,
            'token_id' => null,
        ]);
    }

    public function erc721(?string $tokenId = null): self
    {
        return $this->state([
            'token_type' => TokenType::ERC721,
            'token_id' => $tokenId ?? (string) random_int(1, PHP_INT_MAX),
            'symbol' => null,
            'decimals' => null,
        ]);
    }

    public function erc1155(?string $tokenId = null, string $quantity = '0'): self
    {
        return $this->state([
            'token_type' => TokenType::ERC1155,
            'token_id' => $tokenId ?? (string) random_int(1, PHP_INT_MAX),
            'quantity' => $quantity,
            'symbol' => null,
            'decimals' => null,
        ]);
    }
}
