<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('client')->after('password');
            }
            if (!Schema::hasColumn('users', 'has_invested')) {
                $table->boolean('has_invested')->default(false)->after('activated');
            }
            if (!Schema::hasColumn('users', 'investment_start_at')) {
                $table->timestamp('investment_start_at')->nullable()->after('has_invested');
            }
            if (!Schema::hasColumn('users', 'investment_processed')) {
                $table->boolean('investment_processed')->default(false)->after('investment_start_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'has_invested', 'investment_start_at', 'investment_processed']);
        });
    }
};
