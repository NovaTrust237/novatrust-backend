<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->after('name');
            }
        });

        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'investment_principal_cfa')) {
                $table->bigInteger('investment_principal_cfa')->default(0)->after('virtual_balance_cfa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'email')) {
                $table->dropColumn('email');
            }
        });
        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'investment_principal_cfa')) {
                $table->dropColumn('investment_principal_cfa');
            }
        });
    }
};
