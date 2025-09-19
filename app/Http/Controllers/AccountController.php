<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class AccountController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'integer', 'exists:account_types,id'],
            'balance' => ['nullable', 'numeric'],
            'due' => ['nullable', 'date'],
            'avoid_interest_date' => ['nullable', 'date'],
            'monthly_due_date_day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ]);

        $account = Account::create($data);
        return response()->json($account->load('accountType'), Response::HTTP_CREATED);
    }

    public function show(Account $account)
    {
        return response()->json($account->load('accountType'));
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'account_type_id' => ['sometimes', 'required', 'integer', 'exists:account_types,id'],
            'balance' => ['sometimes', 'nullable', 'numeric'],
            'due' => ['sometimes', 'nullable', 'date'],
            'avoid_interest_date' => ['sometimes', 'nullable', 'date'],
            'monthly_due_date_day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
        ]);

        $account->update($data);
        return response()->json($account->load('accountType'));
    }

    public function destroy(Account $account)
    {
        $account->delete();
        return response()->noContent();
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = Account::query()->with('accountType');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhereHas('accountType', fn ($r) => $r->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = $validated['per_page'] ?? 15;
        $accounts = $q->orderBy('name')->paginate($perPage)->withQueryString();

        return response()->json([
            'accounts' => $accounts,
        ]);
    }
}
