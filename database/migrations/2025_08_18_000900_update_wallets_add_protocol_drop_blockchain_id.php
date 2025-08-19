<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add protocol column if missing
        if (! Schema::hasColumn('wallets', 'protocol')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->string('protocol')->default('evm')->after('owner_id');
            });
        }

        // Drop blockchain_id foreign key and column if it exists
        if (Schema::hasColumn('wallets', 'blockchain_id')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropForeign(['blockchain_id']);
                $table->dropColumn('blockchain_id');
            });
        }
    }

    public function down(): void
    {
        // Re-add blockchain_id (nullable) and foreign key to blockchains
        if (! Schema::hasColumn('wallets', 'blockchain_id')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->foreignId('blockchain_id')->nullable()->constrained('blockchains');
            });
        }

        // Drop protocol column
        if (Schema::hasColumn('wallets', 'protocol')) {
            Schema::table('wallets', function (Blueprint $table) {
                $table->dropColumn('protocol');
            });
        }
    }
};
