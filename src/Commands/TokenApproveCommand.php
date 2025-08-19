<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;
use Roberts\Web3Laravel\Services\TokenService;

class TokenApproveCommand extends Command
{
    protected $signature = 'web3:token:approve 
                            {token : Token ID}
                            {owner : Wallet ID or address}
                            {spender : Spender address}
                            {amount : Amount to approve (human or raw)}
                            {--raw : Treat amount as raw base units}';

    protected $description = 'Approve a spender for a token allowance (chain-agnostic)';

    public function handle(TokenService $tokenService): int
    {
        $tokenId = $this->argument('token');
        $ownerInput = $this->argument('owner');
        $spender = (string) $this->argument('spender');
        $amountInput = (string) $this->argument('amount');
        $treatRaw = (bool) $this->option('raw');

        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return self::FAILURE;
        }

        $wallet = is_numeric($ownerInput)
            ? Wallet::find($ownerInput)
            : Wallet::byAddress($ownerInput)->first();
        if (! $wallet) {
            $this->error("Wallet not found: {$ownerInput}");

            return self::FAILURE;
        }

        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        $adapter = $router->for($wallet->protocol);
        if (! $adapter->validateAddress($spender)) {
            $this->error("Invalid spender address for protocol {$wallet->protocol->value}: {$spender}");

            return self::FAILURE;
        }
        $spenderNorm = $adapter->normalizeAddress($spender);

        // Parse amount if not raw and token is ERC-20-like
        $amount = $treatRaw ? $amountInput : ($token->getTokenType() === 'erc20' ? $token->parseAmount($amountInput) : $amountInput);

        try {
            $this->info('Preparing approval...');
            $this->line("Token: {$token->contract->address}");
            $this->line("Owner: {$wallet->address}");
            $this->line("Spender: {$spenderNorm}");
            $this->line("Amount (raw): {$amount}");

            // If running non-interactively (e.g., in tests with --no-interaction), auto-approve.
            $shouldProceed = $this->getOutput()->isVerbose() // verbose implies non-interactive sometimes
                || ! $this->input->isInteractive()
                || $this->option('no-interaction');
            if (! $shouldProceed && ! $this->confirm('Proceed with approval?')) {
                $this->info('Approval cancelled');

                return self::SUCCESS;
            }

            if ($wallet->protocol->isEvm()) {
                // Defer to TokenService for ERC-20 approve call. During unit tests, avoid model events to skip RPC.
                if (app()->runningUnitTests()) {
                    $tx = \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($tokenService, $token, $wallet, $spenderNorm, $amount) {
                        return $tokenService->approve($token, $wallet, $spenderNorm, $amount);
                    });
                } else {
                    $tx = $tokenService->approve($token, $wallet, $spenderNorm, $amount);
                }
                $this->info("Approval transaction created with ID: {$tx->id}");
                $this->line('Status: '.$tx->statusValue());
            } elseif ($wallet->protocol->isSolana()) {
                $sig = $adapter->approveToken($token, $wallet, $spenderNorm, $amount);
                $this->info('SPL token approve submitted');
                $this->line("Signature: {$sig}");
            } else {
                throw new \RuntimeException('Approvals for '.$wallet->protocol->value.' are not implemented yet');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Approval failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
