<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'due')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                // Change column type to DATE (nullable)
                DB::statement('ALTER TABLE `accounts` MODIFY `due` DATE NULL');
            }
            // On sqlite and others, skip since ALTER MODIFY not supported; schema stays compatible for tests
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'due')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                // Revert to DECIMAL(12,2) NULL
                DB::statement('ALTER TABLE `accounts` MODIFY `due` DECIMAL(12,2) NULL');
            }
        }
    }
};
