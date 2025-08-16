<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Wallet;

class WalletListCommand extends Command
{
    public $signature = 'web3:wallet:list {--ownerType=} {--ownerId=}';

    public $description = 'List wallets with optional owner filter.';

    public function handle(): int
    {
        $ownerType = $this->option('ownerType');
        $ownerId = $this->option('ownerId');

        $query = Wallet::query();
        if ($ownerType && $ownerId) {
            $query->where('owner_type', $ownerType)->where('owner_id', $ownerId);
        }

        $rows = $query->limit(50)->get()->map(function (Wallet $w) {
            return [
                'id' => $w->id,
                'address' => $w->address,
                'blockchain_id' => $w->blockchain_id,
                'owner' => $w->owner_type ? ($w->owner_type.'#'.$w->owner_id) : '-',
                'active' => $w->is_active ? 'yes' : 'no',
                'last_used_at' => optional($w->last_used_at)->toDateTimeString(),
                'key_preview' => (string) ($w->maskedKey() ?? ''),
            ];
        })->all();

        $this->table(['ID','Address','Chain','Owner','Active','Last Used','Key'], $rows);

        return self::SUCCESS;
    }
}
