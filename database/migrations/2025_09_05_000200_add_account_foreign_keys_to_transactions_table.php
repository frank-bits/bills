<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'account_type_id')) {
                $table->foreignId('account_type_id')->nullable()->after('date')->constrained('account_types')->cascadeOnUpdate()->nullOnDelete();
            }
            if (! Schema::hasColumn('transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->after('account_type_id')->constrained('accounts')->cascadeOnUpdate()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'account_id')) {
                $table->dropConstrainedForeignId('account_id');
            }
            if (Schema::hasColumn('transactions', 'account_type_id')) {
                $table->dropConstrainedForeignId('account_type_id');
            }
        });
    }
};
