<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets');
            $table->foreignId('blockchain_id')->nullable()->constrained('blockchains');
            $table->foreignId('contract_id')->nullable()->constrained('contracts');
            $table->string('to', 42)->nullable()->index();
            $table->string('from', 42)->nullable()->index();
            $table->string('function')->nullable();
            $table->json('function_params')->nullable();
            $table->string('value')->default('0'); // wei
            $table->string('token_quantity')->nullable(); // wei, for token transfers/mints
            $table->unsignedBigInteger('gas_limit')->nullable();
            $table->string('gwei')->nullable(); // legacy gasPrice (wei)
            $table->string('fee_max')->nullable(); // maxFeePerGas (wei)
            $table->string('priority_max')->nullable(); // maxPriorityFeePerGas (wei)
            $table->boolean('is_1559')->default(true);
            $table->unsignedBigInteger('nonce')->nullable();
            $table->unsignedInteger('chain_id')->nullable();
            $table->string('data')->nullable();
            $table->string('access_list')->nullable(); // optional serialized json for EIP-2930
            $table->string('status')->default('pending'); // pending|submitted|confirmed|failed
            $table->string('tx_hash')->nullable()->unique();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
