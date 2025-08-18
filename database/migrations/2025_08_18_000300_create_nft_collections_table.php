<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nft_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol', 32);
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->text('banner_url')->nullable();
            $table->text('external_url')->nullable();
            $table->enum('standard', ['erc721', 'erc1155']);
            $table->decimal('total_supply', 78, 0)->nullable(); // Nullable for unlimited/unknown
            $table->decimal('floor_price', 78, 0)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->unique('contract_id');
            $table->index('standard');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nft_collections');
    }
};
