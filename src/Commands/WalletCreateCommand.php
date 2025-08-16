<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Services\WalletService;

class WalletCreateCommand extends Command
{
    public $signature = 'web3:wallet:create {--ownerType=} {--ownerId=} {--chainId=}';

    public $description = 'Create a new wallet (secp256k1) optionally tied to an owner and chain.';

    public function handle(): int
    {
        $ownerType = $this->option('ownerType');
        $ownerId = $this->option('ownerId');
        $chainId = $this->option('chainId');

        $owner = null;
        if ($ownerType && $ownerId) {
            if (! class_exists($ownerType)) {
                $this->error('Owner type not found: '.$ownerType);

                return self::FAILURE;
            }
            $owner = $ownerType::query()->find($ownerId);
            if (! $owner) {
                throw new ModelNotFoundException("Owner not found for {$ownerType} id={$ownerId}");
            }
        }

        $blockchain = null;
        if (is_numeric($chainId)) {
            $blockchain = Blockchain::query()->where('chain_id', (int) $chainId)->first();
            if (! $blockchain) {
                $this->warn('Blockchain with chain_id='.$chainId.' not found, leaving blockchain_id null.');
            }
        }

        $service = app(WalletService::class);
        $wallet = $service->create([], $owner, $blockchain);

        $this->info('Wallet created: '.$wallet->address);
        $this->line('Encrypted key stored.');

        return self::SUCCESS;
    }
}
