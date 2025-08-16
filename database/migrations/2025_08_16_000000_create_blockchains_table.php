<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchains', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation', 16); // e.g., ETH, BASE
            $table->unsignedBigInteger('chain_id'); // EVM chain id
            $table->string('rpc'); // primary RPC URL
            $table->string('scanner')->nullable(); // block explorer base URL
            $table->boolean('evm')->default(true); // EVM-compatible
            // Suggested helpful fields
            $table->boolean('supports_eip1559')->default(true);
            $table->string('native_symbol', 16)->default('ETH');
            $table->unsignedTinyInteger('native_decimals')->default(18);
            $table->json('rpc_alternates')->nullable(); // backup RPC endpoints
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['chain_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchains');
    }
};
