<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Wallet;

class WalletListCommand extends Command
{
    public $signature = 'web3:wallet:list {--ownerId=}';

    public $description = 'List wallets with optional owner filter.';

    public function handle(): int
    {
        $ownerId = $this->option('ownerId');

        $query = Wallet::query();
        if ($ownerId) {
            $query->where('owner_id', $ownerId);
        }

        /** @var \Illuminate\Support\Collection<int,Wallet> $collection */
        $collection = $query->limit(50)->get();
        $rows = $collection->map(function (Wallet $w): array {
            return [
                'id' => $w->id,
                'address' => $w->address,
                'blockchain_id' => $w->blockchain_id,
                'owner_id' => $w->owner_id ?? '-',
                'active' => $w->is_active ? 'yes' : 'no',
                'last_used_at' => optional($w->last_used_at)->toDateTimeString(),
                'key_preview' => (string) ($w->maskedKey() ?? ''),
            ];
        })->all();

        $this->table(['ID', 'Address', 'Chain', 'Owner', 'Active', 'Last Used', 'Key'], $rows);

        return self::SUCCESS;
    }
}
