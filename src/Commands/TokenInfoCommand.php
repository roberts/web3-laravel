<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\TokenService;

class TokenInfoCommand extends Command
{
    protected $signature = 'web3:token:info {token : Token ID}';

    protected $description = 'Display token information and metadata';

    public function handle(TokenService $tokenService): int
    {
        $tokenId = $this->argument('token');

        // Find token
        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token with ID {$tokenId} not found");

            return self::FAILURE;
        }

        try {
            $this->info('Token Information');
            $this->line('================');
            $this->line("ID: {$token->id}");
            $this->line("Type: {$token->token_type->value}");
            $this->line("Contract: {$token->contract->address}");
            $this->line("Blockchain: {$token->contract->blockchain->name} (Chain ID: {$token->contract->blockchain->chain_id})");
            $this->line("Quantity: {$token->quantity}");

            if ($token->token_id) {
                $this->line("Token ID: {$token->token_id}");
            }

            // Get metadata
            $this->line("\nFetching metadata...");
            $metadata = $tokenService->getTokenMetadata($token);

            if (! empty($metadata)) {
                $this->line("\nToken Metadata");
                $this->line('==============');

                foreach ($metadata as $key => $value) {
                    if ($value !== null && $value !== '') {
                        $this->line(ucfirst($key).": {$value}");
                    }
                }
            } else {
                $this->line("No metadata available or contract doesn't support standard metadata functions.");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to fetch token info: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
