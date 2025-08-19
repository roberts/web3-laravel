<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('symbol', 32); // Token symbol (e.g., 'ETH', 'USDC')
            $table->string('name'); // Token name (e.g., 'Ethereum', 'USD Coin')
            $table->unsignedTinyInteger('decimals'); // Number of decimal places
            $table->decimal('total_supply', 78, 0)->nullable(); // Total token supply

            // Token metadata fields (all optional)
            $table->string('icon_url')->nullable();
            $table->text('description')->nullable();
            $table->string('website_url')->nullable();
            $table->string('twitter_url')->nullable();
            $table->string('telegram_url')->nullable();

            // Market data (optional)
            $table->decimal('price_usd', 20, 8)->nullable();
            $table->decimal('market_cap_usd', 20, 2)->nullable();
            $table->decimal('volume_24h_usd', 20, 2)->nullable();
            $table->decimal('percent_change_24h', 5, 2)->nullable();
            $table->timestamp('price_updated_at')->nullable();

            $table->timestamps();
            $table->unique(['contract_id']); // One token per contract
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
