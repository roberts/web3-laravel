<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_nfts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('nft_collection_id')->constrained('nft_collections')->cascadeOnDelete();
            $table->string('token_id', 78); // Support very large token IDs
            $table->decimal('quantity', 78, 0)->default(1); // For ERC-1155
            $table->text('metadata_uri')->nullable();
            $table->json('metadata')->nullable(); // Cached metadata
            $table->json('traits')->nullable();
            $table->unsignedInteger('rarity_rank')->nullable();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamps();

            $table->unique(['wallet_id', 'nft_collection_id', 'token_id'], 'wallet_nft_token_unique');
            $table->index('wallet_id');
            $table->index('nft_collection_id');
            $table->index('token_id');
            $table->index('rarity_rank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_nfts');
    }
};
