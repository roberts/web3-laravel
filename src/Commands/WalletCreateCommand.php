<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Roberts\Web3Laravel\Models\Blockchain;
use Roberts\Web3Laravel\Models\Wallet;

class WalletCreateCommand extends Command
{
    public $signature = 'web3:wallet:create {--ownerType=} {--ownerId=} {--chainId=} {--blockchainId=} {--protocol=} {--network=}';

    public $description = 'Create a new wallet optionally tied to an owner and a specified blockchain (EVM or Solana).';

    public function handle(): int
    {
        $ownerType = $this->option('ownerType');
        $ownerId = $this->option('ownerId');
        $chainId = $this->option('chainId');
        $blockchainId = $this->option('blockchainId');
        $protocol = $this->option('protocol');
        $network = $this->option('network');

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

        // Attributes passed to adapters (e.g., network for Bitcoin tb/bc, etc.)
        $attributes = [];
        if (is_string($network) && $network !== '') {
            $attributes['network'] = $network;
        }

        if ($blockchain) {
            $wallet = Wallet::createForBlockchain($blockchain, $attributes, $owner);
        } else {
            // Use protocol if provided; otherwise default to EVM
            $proto = is_string($protocol) && $protocol !== '' ? $protocol : 'evm';
            $wallet = Wallet::createForProtocolValue($proto, $attributes, $owner, null);
        }

        $this->info('Wallet created: '.$wallet->address);
        $this->line('Protocol: '.$wallet->protocol->value.' | Encrypted key stored.');
        if (! empty($wallet->public_key)) {
            $this->line('Public Key: '.$wallet->public_key);
        }

        return self::SUCCESS;
    }
}
