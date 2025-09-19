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

        $types = DB::table('transactions')
            ->select('type')
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type')
            ->map(fn ($t) => trim((string) $t))
            ->filter(fn ($t) => $t !== '')
            ->unique()
            ->values();

        $now = now();
        foreach ($types as $name) {
            $exists = DB::table('account_types')->where('name', $name)->exists();
            if (! $exists) {
                DB::table('account_types')->insert([
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op: data migration is intentionally irreversible.
    }
};
