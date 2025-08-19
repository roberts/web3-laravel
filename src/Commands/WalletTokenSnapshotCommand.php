<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Token;
use Roberts\Web3Laravel\Services\WalletTokenService;

class WalletTokenSnapshotCommand extends Command
{
    protected $signature = 'web3:wallet:snapshot
                            {token : Token ID}
                            {addresses* : One or more wallet addresses}
                            {--allowance= : Optional spender address to snapshot allowances too}';

    protected $description = 'Snapshot token balances for wallets (and optionally allowances).';

    public function handle(WalletTokenService $service): int
    {
        $tokenId = (int) $this->argument('token');
        $addresses = (array) $this->argument('addresses');
        $spender = $this->option('allowance');

        $token = Token::find($tokenId);
        if (! $token) {
            $this->error("Token {$tokenId} not found");

            return self::FAILURE;
        }

        $this->info('Snapshotting balances...');
        $rows = $service->snapshotBalances($token, $addresses);
        $this->line('Updated rows: '.count($rows));

        if ($spender) {
            $this->info('Snapshotting allowances...');
            $count = 0;
            foreach ($rows as $row) {
                $service->snapshotAllowance($token, $row->wallet, $spender);
                $count++;
            }
            $this->line('Allowances checked for '.$count.' wallets');
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
