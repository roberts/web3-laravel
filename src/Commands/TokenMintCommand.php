<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\TokenService;
use Roberts\Web3Laravel\Support\Address;

class TokenMintCommand extends Command
{
    protected $signature = 'web3:token:mint 
                            {token : Token ID}
                            {wallet : Wallet ID or address}
                            {to : Recipient address}
                            {amount : Amount to mint}
                            {--token-id= : Token ID for NFTs}
                            {--uri= : Token URI for NFTs}';

    protected $description = 'Mint new tokens';

    public function handle(TokenService $tokenService): int
    {
        $tokenId = $this->argument('token');
        $walletInput = $this->argument('wallet');
        $to = $this->argument('to');
        $amount = $this->argument('amount');
        $nftTokenId = $this->option('token-id');
        $uri = $this->option('uri');

        // Find token
        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return self::FAILURE;
        }

        // Find wallet (minter)
        $wallet = is_numeric($walletInput)
            ? Wallet::find($walletInput)
            : Wallet::where('address', \Roberts\Web3Laravel\Support\Address::normalize($walletInput))->first();

        if (! $wallet) {
            $this->error("Wallet not found: {$walletInput}");

            return self::FAILURE;
        }

        // Validate addresses
    if (! Address::isValidEvm($to)) {
            $this->error("Invalid recipient address: {$to}");

            return self::FAILURE;
        }

        try {
            $this->info('Preparing mint transaction...');
            $this->line("Token: {$token->contract->address} ({$token->getTokenType()})");
            $this->line("Minter: {$wallet->address}");
            $this->line("To: {$to}");
            $this->line("Amount: {$amount}");
            if ($nftTokenId) {
                $this->line("Token ID: {$nftTokenId}");
            }
            if ($uri) {
                $this->line("Token URI: {$uri}");
            }

            if (! $this->confirm('Proceed with minting?')) {
                $this->info('Minting cancelled');

                return self::SUCCESS;
            }

            $data = $uri ? ['uri' => $uri] : [];
            $transaction = $tokenService->mint($token, $wallet, $to, $amount, $nftTokenId, $data);

            $this->info("Mint transaction created with ID: {$transaction->id}");
            $this->line("Status: {$transaction->status->value}");
            $this->line('The transaction will be processed asynchronously.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Minting failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    // Validation provided by Support\Address
}
