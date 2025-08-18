<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\TokenService;

class TokenTransferCommand extends Command
{
    protected $signature = 'web3:token:transfer 
                            {token : Token ID}
                            {from : Wallet ID or address}
                            {to : Recipient address}
                            {amount : Amount to transfer}
                            {--token-id= : Token ID for NFTs}';

    protected $description = 'Transfer tokens from one address to another';

    public function handle(TokenService $tokenService): int
    {
        $tokenId = $this->argument('token');
        $fromInput = $this->argument('from');
        $to = $this->argument('to');
        $amount = $this->argument('amount');
        $nftTokenId = $this->option('token-id');

        // Find token
        $token = Token::find($tokenId);
        if (!$token) {
            $this->error("Token with ID {$tokenId} not found");
            return self::FAILURE;
        }

        // Find wallet
        $wallet = is_numeric($fromInput) 
            ? Wallet::find($fromInput)
            : Wallet::where('address', strtolower($fromInput))->first();
            
        if (!$wallet) {
            $this->error("Wallet not found: {$fromInput}");
            return self::FAILURE;
        }

        // Validate addresses
        if (!$this->isValidAddress($to)) {
            $this->error("Invalid recipient address: {$to}");
            return self::FAILURE;
        }

        try {
            $this->info("Preparing transfer...");
            $this->line("Token: {$token->contract->address} ({$token->token_type->value})");
            $this->line("From: {$wallet->address}");
            $this->line("To: {$to}");
            $this->line("Amount: {$amount}");
            if ($nftTokenId) {
                $this->line("Token ID: {$nftTokenId}");
            }

            if (!$this->confirm('Proceed with transfer?')) {
                $this->info('Transfer cancelled');
                return self::SUCCESS;
            }

            $transaction = $tokenService->transfer($token, $wallet, $to, $amount, $nftTokenId);
            
            $this->info("Transfer transaction created with ID: {$transaction->id}");
            $this->line("Status: {$transaction->status->value}");
            $this->line("The transaction will be processed asynchronously.");
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Transfer failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function isValidAddress(string $address): bool
    {
        return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
}
