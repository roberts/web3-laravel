<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Models\Wallet;
use Roberts\Web3Laravel\Protocols\ProtocolRouter;

class NativeTransferCommand extends Command
{
    protected $signature = 'web3:native:transfer {from_wallet_id} {to_address} {amount}';
    protected $description = 'Transfer native currency (ETH/SOL, etc.) using the protocol adapter';

    public function handle(): int
    {
        $from = Wallet::findOrFail((int) $this->argument('from_wallet_id'));
        $to = (string) $this->argument('to_address');
        $amount = (string) $this->argument('amount'); // base units expected

        /** @var ProtocolRouter $router */
        $router = app(ProtocolRouter::class);
        $adapter = $router->for($from->protocol);

        $sig = $adapter->transferNative($from, $to, $amount);
        $this->info("Submitted: {$sig}");
        return self::SUCCESS;
    }
}
