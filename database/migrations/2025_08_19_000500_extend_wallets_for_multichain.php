<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (! Schema::hasColumn('wallets', 'public_key')) {
                $table->text('public_key')->nullable()->after('key');
            }
            if (! Schema::hasColumn('wallets', 'derivation_path')) {
                $table->string('derivation_path')->nullable()->after('public_key');
            }
            if (! Schema::hasColumn('wallets', 'key_scheme')) {
                $table->string('key_scheme')->nullable()->after('derivation_path');
            }
            if (! Schema::hasColumn('wallets', 'account_status')) {
                $table->string('account_status')->nullable()->after('last_used_at');
            }
            if (! Schema::hasColumn('wallets', 'network')) {
                $table->string('network')->nullable()->after('protocol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            foreach (['public_key', 'derivation_path', 'key_scheme', 'account_status', 'network'] as $col) {
                if (Schema::hasColumn('wallets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
