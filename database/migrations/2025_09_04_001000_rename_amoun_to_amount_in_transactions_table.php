<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'amoun') && ! Schema::hasColumn('transactions', 'amount')) {
            // Use direct SQL to avoid requiring doctrine/dbal
            DB::statement('ALTER TABLE `transactions` CHANGE `amoun` `amount` DECIMAL(10,2) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'amount') && ! Schema::hasColumn('transactions', 'amoun')) {
            DB::statement('ALTER TABLE `transactions` CHANGE `amount` `amoun` DECIMAL(10,2) NOT NULL');
        }
    }
};
