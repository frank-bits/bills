<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'type')) {
            DB::statement('ALTER TABLE `transactions` DROP COLUMN `type`');
        }
    }

    public function down(): void
    {
        // Recreate the column if needed (string 100); values cannot be restored
        if (Schema::hasTable('transactions') && ! Schema::hasColumn('transactions', 'type')) {
            DB::statement('ALTER TABLE `transactions` ADD `type` VARCHAR(100) NULL AFTER `transaction`');
        }
    }
};
