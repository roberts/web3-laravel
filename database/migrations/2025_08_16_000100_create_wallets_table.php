<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->string('address')->unique();
            $table->text('key')->nullable(); // encrypted private key (Crypt)
            $table->string('wallet_type')->default('custodial'); // custodial, shared, external
            $table->foreignId('owner_id')->nullable()->constrained('users'); // fixed owner to users table
            $table->string('protocol')->default('evm'); // BlockchainProtocol: evm|solana|bitcoin|sui|xrpl|cardano|hedera|ton
            $table->string('network')->nullable(); // mainnet, testnet, devnet, etc.
            // Multichain key info
            $table->text('public_key')->nullable();
            $table->string('derivation_path')->nullable();
            $table->string('key_scheme')->nullable(); // e.g., secp256k1, ed25519
            // wallet activity
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('account_status')->nullable(); // created, funded, initialized, etc.
            $table->json('meta')->nullable();
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
