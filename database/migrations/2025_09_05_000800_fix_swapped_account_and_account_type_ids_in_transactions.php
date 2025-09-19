<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('accounts') || ! Schema::hasTable('account_types')) {
            return;
        }

        // Find rows where account_type_id doesn't match an account_types id but does match an accounts id,
        // and account_id doesn't match an accounts id but does match an account_types id.
        $query = DB::table('transactions as t')
            ->leftJoin('account_types as at', 'at.id', '=', 't.account_type_id')
            ->leftJoin('accounts as a', 'a.id', '=', 't.account_id')
            ->leftJoin('account_types as at2', 'at2.id', '=', 't.account_id')
            ->leftJoin('accounts as a2', 'a2.id', '=', 't.account_type_id')
            ->whereNull('at.id')
            ->whereNull('a.id')
            ->whereNotNull('at2.id')
            ->whereNotNull('a2.id')
            ->select('t.id', 't.account_type_id', 't.account_id');

        $count = 0;
        $query->orderBy('t.id')->chunk(500, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                DB::table('transactions')
                    ->where('id', $row->id)
                    ->update([
                        'account_type_id' => $row->account_id,
                        'account_id' => $row->account_type_id,
                    ]);
                $count++;
            }
        });

        if ($count > 0) {
            info("Fixed swapped account/account_type on {$count} transactions");
        }
    }

    public function down(): void
    {
        // Irreversible safely.
    }
};
