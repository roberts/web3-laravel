<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\TokenService;

class TokenBalanceCommand extends Command
{
    protected $signature = 'web3:token:balance 
                            {token : Token ID}
                            {address : Address to check balance for}
                            {--token-id= : Specific token ID for ERC-721/1155}
                            {--format : Format ERC-20 amounts with decimals}';

    protected $description = 'Check token balance for an address';

    public function handle(TokenService $tokenService): int
    {
        $tokenId = $this->argument('token');
        $address = $this->argument('address');
        $nftTokenId = $this->option('token-id');
        $format = $this->option('format');

        // Find token
        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return self::FAILURE;
        }

        // Validate address
        if (! $this->isValidAddress($address)) {
            $this->error("Invalid address: {$address}");

            return self::FAILURE;
        }

        try {
            $this->info('Checking token balance...');
            $this->line("Token: {$token->contract->address} ({$token->token_type->value})");
            $this->line("Address: {$address}");
            if ($nftTokenId) {
                $this->line("Token ID: {$nftTokenId}");
            }

            $balance = $tokenService->balanceOf($token, $address, $nftTokenId);

            if ($format && $token->isERC20()) {
                $metadata = $tokenService->getTokenMetadata($token);
                $decimals = $metadata['decimals'] ?? 18;
                $formattedBalance = $token->formatAmount($balance);
                $symbol = $metadata['symbol'] ?? 'TOKEN';

                $this->info("Balance: {$formattedBalance} {$symbol}");
                $this->line("Raw balance: {$balance}");
            } else {
                $this->info("Balance: {$balance}");
            }

            // Additional info for NFTs
            if ($token->isERC721() && $nftTokenId) {
                try {
                    $owner = $tokenService->ownerOf($token, $nftTokenId);
                    $isOwner = strtolower($owner) === strtolower($address);
                    $this->line("Owner: {$owner} ".($isOwner ? '(matches queried address)' : ''));
                } catch (\Exception $e) {
                    $this->line("Could not fetch owner info: {$e->getMessage()}");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Balance check failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
}
