<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Enums\BlockchainProtocol;
use Roberts\Web3Laravel\Services\WalletService;

class WalletCreateCommand extends Command
{
    public $signature = 'web3:wallet:create {--ownerType=} {--ownerId=} {--chainId=} {--blockchainId=} {--protocol=}';

    public $description = 'Create a new wallet optionally tied to an owner and a specified blockchain (EVM or Solana).';

    public function handle(): int
    {
        $ownerType = $this->option('ownerType');
        $ownerId = $this->option('ownerId');
        $chainId = $this->option('chainId');
        $blockchainId = $this->option('blockchainId');
        $protocol = $this->option('protocol');

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
        if (is_numeric($blockchainId)) {
            $blockchain = Blockchain::query()->find((int) $blockchainId);
            if (! $blockchain) {
                $this->error('Blockchain with id='.$blockchainId.' not found.');
                return self::FAILURE;
            }
        } elseif (is_numeric($chainId)) {
            $blockchain = Blockchain::query()->where('chain_id', (int) $chainId)->first();
            if (! $blockchain) {
                $this->error('Blockchain with chain_id='.$chainId.' not found.');
                return self::FAILURE;
            }
        } elseif (is_string($protocol) && $protocol !== '') {
            $blockchain = Blockchain::query()
                ->where('protocol', $protocol)
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->first();
            if (! $blockchain) {
                $this->error('No active blockchain found for protocol='.$protocol.'.');
                return self::FAILURE;
            }
        }

        $service = app(WalletService::class);
        if ($blockchain) {
            $wallet = $service->createForBlockchain($blockchain, [], $owner);
        } else {
            // Use protocol if provided, else default to EVM
            $proto = is_string($protocol) ? strtolower($protocol) : 'evm';
            $enum = $proto === 'solana' ? BlockchainProtocol::SOLANA : BlockchainProtocol::EVM;
            $wallet = $service->createForProtocol($enum, [], $owner);
        }

        $this->info('Wallet created: '.$wallet->address);
    $this->line('Protocol: '.$wallet->protocol->value.' | Encrypted key stored.');

        return self::SUCCESS;
    }
}
