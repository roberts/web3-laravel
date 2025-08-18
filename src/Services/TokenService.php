<?php

namespace Roberts\Web3Laravel\Services;

use Roberts\Web3Laravel\Models\Contract;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Transaction;
use Roberts\Web3Laravel\Models\Wallet;

/**
 * Token interaction service for ERC-20, ERC-721, and ERC-1155 operations.
 * Handles minting, transfers, approvals, and balance queries.
 */
class TokenService
{
    public function __construct(
        protected ContractCaller $contractCaller,
        protected TransactionService $transactionService
    ) {}

    /**
     * Get token balance for an address.
     * Works with ERC-20, ERC-721, and ERC-1155.
     */
    public function balanceOf(Token $token, string $address, ?string $tokenId = null): string
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        $params = match ($token->getTokenType()) {
            'erc20' => [$address],
            default => throw new \InvalidArgumentException('Unsupported token type: '.$token->getTokenType())
        };

        $result = $this->contractCaller->call(
            $contract->abi,
            $contract->address,
            'balanceOf',
            $params
        );

        return $result[0] ?? '0';
    }

    /**
     * Transfer tokens from one address to another.
     * Creates a Transaction model for async processing.
     */
    public function transfer(Token $token, Wallet $from, string $to, string $amount, ?string $tokenId = null): Transaction
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        [$functionName, $params, $value] = match ($token->getTokenType()) {
            'erc20' => ['transfer', [$to, $amount], '0x0'],
            default => throw new \InvalidArgumentException('Unsupported token type: '.$token->getTokenType())
        };

        $data = $this->encodeFunction($contract->abi, $functionName, $params);

        return Transaction::create([
            'wallet_id' => $from->id,
            'contract_id' => $contract->id,
            'to' => $contract->address,
            'value' => $value,
            'data' => $data,
            'function_params' => [
                'function' => $functionName,
                'params' => $params,
            ],
            'meta' => [
                'token_operation' => 'transfer',
                'token_id' => $token->id,
                'amount' => $amount,
                'recipient' => $to,
            ],
        ]);
    }

    /**
     * Approve spender to transfer tokens on behalf of owner.
     * ERC-20 and ERC-721 support.
     */
    public function approve(Token $token, Wallet $owner, string $spender, string $amount): Transaction
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        [$functionName, $params] = match ($token->getTokenType()) {
            'erc20' => ['approve', [$spender, $amount]],
            default => throw new \InvalidArgumentException('Approve not supported for token type: '.$token->getTokenType())
        };

        $data = $this->encodeFunction($contract->abi, $functionName, $params);

        return Transaction::create([
            'wallet_id' => $owner->id,
            'contract_id' => $contract->id,
            'to' => $contract->address,
            'value' => '0x0',
            'data' => $data,
            'function_params' => [
                'function' => $functionName,
                'params' => $params,
            ],
            'meta' => [
                'token_operation' => 'approve',
                'token_id' => $token->id,
                'spender' => $spender,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * Check allowance for ERC-20 tokens.
     */
    public function allowance(Token $token, string $owner, string $spender): string
    {
        if ($token->getTokenType() !== 'erc20') {
            throw new \InvalidArgumentException('Allowance only supported for ERC-20 tokens');
        }

        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        $result = $this->contractCaller->call(
            $contract->abi,
            $contract->address,
            'allowance',
            [$owner, $spender]
        );

        return $result[0] ?? '0';
    }

    /**
     * Mint tokens (if contract supports minting).
     * Attempts standard mint functions: mint, safeMint.
     */
    public function mint(Token $token, Wallet $minter, string $to, string $amount, ?string $tokenId = null, array $data = []): Transaction
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        // Try to determine the correct mint function based on ABI
        $mintFunction = $this->detectMintFunction($contract->abi, $token->getTokenType());

        $params = match ($token->getTokenType()) {
            'erc20' => [$to, $amount],
            'erc721' => $mintFunction === 'safeMint'
                ? [$to, $tokenId ?? $token->token_id ?? '0']
                : [$to],
            'erc1155' => [$to, $tokenId ?? $token->token_id ?? '0', $amount, $data['data'] ?? '0x'],
            default => throw new \InvalidArgumentException('Unsupported token type: '.$token->getTokenType())
        };

        $encodedData = $this->encodeFunction($contract->abi, $mintFunction, $params);

        return Transaction::create([
            'wallet_id' => $minter->id,
            'contract_id' => $contract->id,
            'to' => $contract->address,
            'value' => '0x0',
            'data' => $encodedData,
            'function_params' => [
                'function' => $mintFunction,
                'params' => $params,
            ],
            'meta' => [
                'token_operation' => 'mint',
                'token_id' => $token->id,
                'amount' => $amount,
                'recipient' => $to,
                'mint_token_id' => $tokenId,
            ],
        ]);
    }

    /**
     * Burn tokens (if contract supports burning).
     */
    public function burn(Token $token, Wallet $burner, string $amount, ?string $tokenId = null): Transaction
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        $burnFunction = $this->detectBurnFunction($contract->abi, $token->getTokenType());

        $params = match ($token->getTokenType()) {
            'erc20' => [$amount],
            'erc721' => [$tokenId ?? $token->token_id ?? '0'],
            'erc1155' => [$burner->address, $tokenId ?? $token->token_id ?? '0', $amount],
            default => throw new \InvalidArgumentException('Unsupported token type: '.$token->getTokenType())
        };

        $data = $this->encodeFunction($contract->abi, $burnFunction, $params);

        return Transaction::create([
            'wallet_id' => $burner->id,
            'contract_id' => $contract->id,
            'to' => $contract->address,
            'value' => '0x0',
            'data' => $data,
            'function_params' => [
                'function' => $burnFunction,
                'params' => $params,
            ],
            'meta' => [
                'token_operation' => 'burn',
                'token_id' => $token->id,
                'amount' => $amount,
                'burn_token_id' => $tokenId,
            ],
        ]);
    }

    /**
     * Get token metadata (name, symbol, decimals for ERC-20; name, symbol for NFTs).
     */
    public function getTokenMetadata(Token $token): array
    {
        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        $metadata = [];

        // Common metadata for all token types
        try {
            $metadata['name'] = $this->contractCaller->call($contract->abi, $contract->address, 'name', [])[0] ?? '';
            $metadata['symbol'] = $this->contractCaller->call($contract->abi, $contract->address, 'symbol', [])[0] ?? '';
        } catch (\Throwable $e) {
            // Some contracts might not have name/symbol
        }

        // ERC-20 specific
        if ($token->getTokenType() === 'erc20') {
            try {
                $metadata['decimals'] = (int) ($this->contractCaller->call($contract->abi, $contract->address, 'decimals', [])[0] ?? 18);
                $metadata['totalSupply'] = $this->contractCaller->call($contract->abi, $contract->address, 'totalSupply', [])[0] ?? '0';
            } catch (\Throwable $e) {
                // Some contracts might not have these functions
            }
        }

        return $metadata;
    }

    /**
     * Get owner of an NFT (ERC-721).
     */
    public function ownerOf(Token $token, string $tokenId): string
    {
        if ($token->getTokenType() !== 'erc721') {
            throw new \InvalidArgumentException('ownerOf only supported for ERC-721 tokens');
        }

        $contract = $token->contract;
        if (! $contract || ! $contract->abi) {
            throw new \InvalidArgumentException('Token contract must have ABI defined');
        }

        $result = $this->contractCaller->call(
            $contract->abi,
            $contract->address,
            'ownerOf',
            [$tokenId]
        );

        return $result[0] ?? '';
    }

    /**
     * Encode function call data using ABI.
     */
    protected function encodeFunction(array $abi, string $functionName, array $params): string
    {
        return $this->contractCaller->encodeCallData($abi, $functionName, $params);
    }

    /**
     * Detect the appropriate mint function from ABI.
     */
    protected function detectMintFunction(array $abi, string $tokenType): string
    {
        $possibleFunctions = ['mint', 'safeMint', 'mintTo'];

        foreach ($abi as $item) {
            if (($item['type'] ?? '') === 'function' && in_array($item['name'] ?? '', $possibleFunctions)) {
                return $item['name'];
            }
        }

        // Default fallbacks
        return match ($tokenType) {
            'erc721' => 'safeMint',
            default => 'mint'
        };
    }

    /**
     * Detect the appropriate burn function from ABI.
     */
    protected function detectBurnFunction(array $abi, string $tokenType): string
    {
        $possibleFunctions = ['burn', 'burnFrom'];

        foreach ($abi as $item) {
            if (($item['type'] ?? '') === 'function' && in_array($item['name'] ?? '', $possibleFunctions)) {
                return $item['name'];
            }
        }

        return 'burn'; // Default fallback
    }
}
