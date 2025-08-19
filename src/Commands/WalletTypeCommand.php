<?php

namespace Roberts\Web3Laravel\Commands;

use Illuminate\Console\Command;
use Roberts\Web3Laravel\Enums\WalletType;
use Roberts\Web3Laravel\Models\Wallet;

class WalletTypeCommand extends Command
{
    protected $signature = 'web3:wallet:type 
                            {wallet : Wallet ID or address}
                            {type? : New wallet type (custodial, shared, external)}
                            {--list : List all wallet types}';

    protected $description = 'View or change wallet type';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listTypes();
        }

        $walletInput = $this->argument('wallet');
        $newType = $this->argument('type');

        // Find wallet
        $wallet = is_numeric($walletInput)
            ? Wallet::find($walletInput)
            : Wallet::where('address', \Roberts\Web3Laravel\Support\Address::normalize($walletInput))->first();

        if (! $wallet) {
            $this->error("Wallet not found: {$walletInput}");

            return self::FAILURE;
        }

        if (! $newType) {
            return $this->showWalletType($wallet);
        }

        return $this->changeWalletType($wallet, $newType);
    }

    private function listTypes(): int
    {
        $this->info('Available Wallet Types:');
        $this->line('');

        foreach (WalletType::cases() as $type) {
            $this->line("<info>{$type->value}</info> - {$type->label()}");
            $this->line("  {$type->description()}");
            $this->line('  Can store private key: '.($type->canStorePrivateKey() ? 'Yes' : 'No'));
            $this->line('  Requires external signing: '.($type->requiresExternalSigning() ? 'Yes' : 'No'));
            $this->line('');
        }

        return self::SUCCESS;
    }

    private function showWalletType(Wallet $wallet): int
    {
        $this->info('Wallet Type Information');
        $this->line('======================');
        $this->line("ID: {$wallet->id}");
        $this->line("Address: {$wallet->address}");
        $this->line("Type: {$wallet->wallet_type->value} ({$wallet->getTypeLabel()})");
        $this->line("Description: {$wallet->getTypeDescription()}");
        $this->line('Can store private key: '.($wallet->canStorePrivateKey() ? 'Yes' : 'No'));
        $this->line('Requires external signing: '.($wallet->requiresExternalSigning() ? 'Yes' : 'No'));
        $this->line('Has private key: '.($wallet->key ? 'Yes' : 'No'));

        return self::SUCCESS;
    }

    private function changeWalletType(Wallet $wallet, string $newTypeValue): int
    {
        try {
            $newType = WalletType::from($newTypeValue);
        } catch (\ValueError) {
            $this->error("Invalid wallet type: {$newTypeValue}");
            $this->line('Use --list to see available types');

            return self::FAILURE;
        }

        $oldType = $wallet->wallet_type;

        if ($oldType === $newType) {
            $this->info("Wallet is already of type: {$newType->value}");

            return self::SUCCESS;
        }

        // Check if change is valid
        if ($wallet->key && ! $newType->canStorePrivateKey()) {
            $this->error("Cannot change to {$newType->value}: Wallet has private key but new type cannot store private keys");
            $this->line('Consider removing the private key first or choose a different type');

            return self::FAILURE;
        }

        $this->info('Changing wallet type...');
        $this->line("From: {$oldType->value} ({$oldType->label()})");
        $this->line("To: {$newType->value} ({$newType->label()})");

        if (! $this->confirm('Proceed with change?')) {
            $this->info('Change cancelled');

            return self::SUCCESS;
        }

        $wallet->wallet_type = $newType;
        $wallet->save();

        $this->info('Wallet type changed successfully!');

        if ($newType === WalletType::EXTERNAL && $wallet->key) {
            $this->warn('WARNING: This wallet still has a private key stored. Consider removing it for security.');
        }

        return self::SUCCESS;
    }
}
