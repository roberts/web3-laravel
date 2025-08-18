<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('released_at');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('security_context')->nullable(); // Additional security info
            $table->timestamps();

            // Indexes for performance and security queries
            $table->index(['wallet_id', 'user_id']);
            $table->index(['user_id', 'released_at']);
            $table->index('released_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_releases');
    }
};
