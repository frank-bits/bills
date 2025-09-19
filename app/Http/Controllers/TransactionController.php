<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function index()
    {
    return response()->json(Transaction::with(['accountType','account'])->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transaction' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'integer', 'exists:account_types,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric'],
            'date' => ['required', 'date'],
        ]);

        $transaction = DB::transaction(function () use ($data) {
            // Create the transaction first
            $tx = Transaction::create($data);

            // Apply balance rules for newly created transactions
            BalanceService::applyForNewTransaction($tx);

            return $tx;
        });

        return response()->json($transaction->load(['accountType','account']), Response::HTTP_CREATED);
    }

    public function show(Transaction $transaction)
    {
    return response()->json($transaction->load(['accountType','account']));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $data = $request->validate([
            'transaction' => ['sometimes', 'required', 'string', 'max:255'],
            'account_type_id' => ['sometimes', 'required', 'integer', 'exists:account_types,id'],
            'account_id' => ['sometimes', 'nullable', 'integer', 'exists:accounts,id'],
            'amount' => ['sometimes', 'required', 'numeric'],
            'date' => ['sometimes', 'required', 'date'],
        ]);

    $transaction->update($data);
    return response()->json($transaction->load(['accountType','account']));
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return response()->noContent();
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:date,transaction,amount,account_type,account'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
        ]);

    $query = Transaction::query()->with(['accountType','account']);

        $start = isset($validated['start']) ? Carbon::parse($validated['start'])->startOfDay() : null;
        $end = isset($validated['end']) ? Carbon::parse($validated['end'])->endOfDay() : null;

        if ($start || $end) {
            $query->when($start, fn ($q) => $q->whereDate('date', '>=', $start->toDateString()))
                  ->when($end, fn ($q) => $q->whereDate('date', '<=', $end->toDateString()));
        } else {
            // Default to current month if no range provided
            $monthStart = Carbon::now()->startOfMonth()->toDateString();
            $monthEnd = Carbon::now()->endOfMonth()->toDateString();
            $query->whereBetween('date', [$monthStart, $monthEnd]);
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
                        $query->where(function ($q) use ($search) {
                                $q->where('transaction', 'like', "%{$search}%")
                                    ->orWhereHas('accountType', fn ($r) => $r->where('name', 'like', "%{$search}%"));
                        });
        }

    $perPage = $validated['per_page'] ?? 50;

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        // Apply sorting; support account type via join
        if ($sortBy === 'account_type') {
            $query->leftJoin('account_types', 'account_types.id', '=', 'transactions.account_type_id')
                  ->select('transactions.*')
                  ->orderBy('account_types.name', $sortDir)
                  ->orderBy('transactions.id', 'desc');
        } elseif ($sortBy === 'account') {
            $query->leftJoin('accounts', 'accounts.id', '=', 'transactions.account_id')
                  ->select('transactions.*')
                  ->orderBy('accounts.name', $sortDir)
                  ->orderBy('transactions.id', 'desc');
        } else {
            $query->orderBy($sortBy, $sortDir)
                  ->orderBy('id', 'desc');
        }

    $total = (clone $query)->sum('amount');

        $transactions = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'transactions' => $transactions,
            'running_total' => (float) $total,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // up to ~10MB
        ]);

        $file = $request->file('file');

        $inserted = 0;
        $skipped = 0;
        $errors = [];

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Unable to read uploaded file'], 422);
        }

        // Read header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return response()->json(['message' => 'CSV appears to be empty'], 422);
        }

    // Normalize header keys
        $map = [];
        foreach ($header as $i => $col) {
            $key = strtolower(trim($col));
            $map[$i] = $key;
        }

        $batch = [];
        $now = now();
        $line = 1; // header line is 1

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $data = [];
            foreach ($row as $i => $value) {
                $key = $map[$i] ?? null;
                if ($key) {
                    $data[$key] = trim((string) $value);
                }
            }

            try {
                // Expecting: transaction, account_type_id OR type, amount, date (support old 'amoun')
                if (!isset($data['transaction'], $data['date']) || (!isset($data['amount']) && !isset($data['amoun']))) {
                    throw new \InvalidArgumentException('Missing required columns on line '.$line);
                }

                $date = Carbon::parse($data['date'])->toDateString();
                $amount = isset($data['amount']) ? (float) $data['amount'] : (float) $data['amoun'];

                // Resolve account_type_id
                $accountTypeId = null;
                if (isset($data['account_type_id']) && $data['account_type_id'] !== '') {
                    $accountTypeId = (int) $data['account_type_id'];
                    $exists = DB::table('account_types')->where('id', $accountTypeId)->exists();
                    if (! $exists) {
                        throw new \InvalidArgumentException('Invalid account_type_id on line '.$line);
                    }
                } elseif (isset($data['type']) && $data['type'] !== '') {
                    $name = trim((string) $data['type']);
                    if ($name === '') {
                        throw new \InvalidArgumentException('Empty type on line '.$line);
                    }
                    // Find or create account type by name
                    $found = DB::table('account_types')->where('name', $name)->first();
                    if ($found) {
                        $accountTypeId = $found->id;
                    } else {
                        $accountTypeId = DB::table('account_types')->insertGetId([
                            'name' => $name,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } else {
                    throw new \InvalidArgumentException('Missing account_type_id or type on line '.$line);
                }

                $batch[] = [
                    'transaction' => (string) $data['transaction'],
                    'account_type_id' => $accountTypeId,
                    'amount' => $amount,
                    'date' => $date,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } catch (\Throwable $e) {
                $skipped++;
                if (count($errors) < 10) {
                    $errors[] = 'Line '.$line.': '.$e->getMessage();
                }
            }

            if (count($batch) >= 500) {
                DB::table('transactions')->insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            DB::table('transactions')->insert($batch);
            $inserted += count($batch);
        }

        fclose($handle);

    return response()->json([
            'message' => 'Import complete',
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function template()
    {
        $filename = 'transactions_template.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];
    $content = "transaction,account_type_id,amount,date\n".
       "INV-1001,1,199.99,2025-09-01\n".
       "INV-1002,2,25.50,2025-09-02\n";

        return response($content, 200, $headers);
    }
}
