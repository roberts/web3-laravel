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
            $table->string('token_type'); // erc20|erc721|erc1155
            $table->decimal('quantity', 78, 0)->default(0); // big integer as string
            // Optional fields for token identity:
            $table->string('token_id')->nullable(); // for NFTs (721/1155)
            $table->string('symbol', 32)->nullable(); // for ERC-20
            $table->unsignedTinyInteger('decimals')->nullable(); // for ERC-20
            $table->timestamps();
            $table->index(['contract_id', 'token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
