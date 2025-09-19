<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('account_types')) {
            return;
        }
        if (! Schema::hasColumn('transactions', 'type') || ! Schema::hasColumn('transactions', 'account_type_id')) {
            return;
        }

        // Build a name => id map for account types
        $typeMap = DB::table('account_types')->pluck('id', 'name');
        if ($typeMap->isEmpty()) {
            return;
        }

        // Phase 1: direct matches (fast bulk updates per name)
        foreach ($typeMap as $name => $id) {
            DB::table('transactions')
                ->where('type', $name)
                ->update(['account_type_id' => $id]);
        }

        // Phase 2: handle whitespace mismatches by trimming in PHP and updating per-row
        $remaining = DB::table('transactions')
            ->select('id', 'type')
            ->whereNull('account_type_id')
            ->whereNotNull('type')
            ->get();

        foreach ($remaining as $row) {
            $trimmed = trim((string) $row->type);
            if ($trimmed !== '' && isset($typeMap[$trimmed])) {
                DB::table('transactions')->where('id', $row->id)->update([
                    'account_type_id' => $typeMap[$trimmed],
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'account_type_id')) {
            return;
        }
        // Revert the backfill by nulling the account_type_id values
        DB::table('transactions')->update(['account_type_id' => null]);
    }
};
