<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Services\TokenService;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

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
        $to = (string) $this->argument('to');
        $amountInput = (string) $this->argument('amount');
        $nftTokenId = $this->option('token-id');

        // Find token
        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return self::FAILURE;
        }

        // Find wallet
        $wallet = is_numeric($fromInput)
            ? Wallet::find($fromInput)
            : Wallet::byAddress($fromInput)->first();

        if (! $wallet) {
            $this->error("Wallet not found: {$fromInput}");

            return self::FAILURE;
        }

        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        $adapter = $router->for($wallet->protocol);
        if (! $adapter->validateAddress($to)) {
            $this->error("Invalid recipient address for protocol {$wallet->protocol->value}: {$to}");
            return self::FAILURE;
        }
        $toNormalized = $adapter->normalizeAddress($to);

        // Parse human-readable amount to raw based on token decimals (ERC-20 only)
        $amount = $amountInput;
        if ($token->getTokenType() === 'erc20') {
            $amount = $token->parseAmount($amountInput);
        }

        try {
            $this->info('Preparing transfer...');
            $this->line("Token: {$token->contract->address} ({$token->getTokenType()})");
            $this->line("From: {$wallet->address}");
            $this->line("To: {$toNormalized}");
            $this->line("Amount (raw): {$amount}");
            if ($nftTokenId) {
                $this->line("Token ID: {$nftTokenId}");
            }

            if (! $this->confirm('Proceed with transfer?')) {
                $this->info('Transfer cancelled');

                return self::SUCCESS;
            }

            // Route by protocol
            if ($wallet->protocol->isEvm()) {
                $transaction = $tokenService->transfer($token, $wallet, $toNormalized, $amount, $nftTokenId);
                $this->info("Transfer transaction created with ID: {$transaction->id}");
                $this->line("Status: {$transaction->status->value}");
                $this->line('The transaction will be processed asynchronously.');
            } elseif ($wallet->protocol->isSolana()) {
                $signature = $adapter->transferToken($token, $wallet, $toNormalized, $amount);
                $this->info('SPL token transfer submitted');
                $this->line("Signature: {$signature}");
            } else {
                throw new \RuntimeException('Token transfers for '.$wallet->protocol->value.' are not implemented yet');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Transfer failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    // Address validation moved to Support\Address
}
