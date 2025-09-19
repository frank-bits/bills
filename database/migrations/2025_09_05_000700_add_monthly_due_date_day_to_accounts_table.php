<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'monthly_due_date_day')) {
                $table->unsignedTinyInteger('monthly_due_date_day')->nullable()->after('avoid_interest_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'monthly_due_date_day')) {
                $table->dropColumn('monthly_due_date_day');
            }
        });
    }
};
