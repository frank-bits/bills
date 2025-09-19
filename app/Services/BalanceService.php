<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class BalanceService
{
    /**
     * Apply balance adjustments for a newly created transaction using ID-based rules from config/balance.php.
     * - Overrides: if account_type_id maps to a list of account IDs, increment that account.
     * - Credit card type: decrement its own account balance.
     * - Income type: increment the first account under the configured "check" account type.
     * - Savings type: no-op.
     * - Default: decrement the first account under the configured "check" account type.
     */
    public static function applyForNewTransaction(Transaction $tx): void
    {
        if (! $tx->exists) return;

        $amount = (float) $tx->amount; // may be negative, indicating debit from checking
        if ($amount == 0.0) return;

        $typeId = $tx->account_type_id;
        $accountId = $tx->account_id;

        $checkTypeId = config('balance.check_account_type_id');
        $savingsTypeId = config('balance.savings_account_type_id');
        $creditCardTypeId = config('balance.credit_card_type_id');
        $incomeTypeId = config('balance.income_type_id');
        $overrides = config('balance.increment_overrides', []);

        // 1) Override (non-type-7): add signed amount to the designated account
        if ($typeId && (int)$typeId !== 7 && isset($overrides[$typeId]) && in_array($accountId, $overrides[$typeId] ?? [], true)) {
            self::addToAccountBalance($accountId, $amount);
            return;
        }

        // 2) Credit card: always decrement its own account by absolute amount
        if ($creditCardTypeId && (int)$typeId === (int)$creditCardTypeId) {
            if ($accountId) {
                self::decrementAccountBalance($accountId, abs($amount));
                self::addToAccountBalance(15, $amount);
            }
            return;
        }

        // 2b) Transfer (type=7):
        // - If account is Savings: increase savings by |amount|; add signed amount to Check.
        // - If account is Check: increase check by |amount|; add signed amount to Savings.
        if ((int) $typeId === 7) {
            // $check = self::firstAccountOfType($checkTypeId);
            // $savings = self::firstAccountOfType($savingsTypeId);

            if ($tx->account_id === 16) {
                self::incrementAccountBalance(16, abs($amount));
                self::addToAccountBalance(15, $amount);
                return;
            }

            if ($tx->account_id === 15) {
                self::decrementAccountBalance(16, abs($amount));
                self::addToAccountBalance(15, $amount);
                return;
            }


            // if ($accountId && $savings && $accountId === $savings->id) {
            //     // Destination: savings
            //     self::incrementAccountBalance($savings->id, abs($amount));
            //     if ($check) {
            //         self::addToAccountBalance($check->id, $amount); // signed
            //     }
            //     return;
            // }

            // if ($accountId && $check && $accountId === $check->id) {
            //     // Destination: check
            //     self::incrementAccountBalance($check->id, abs($amount));
            //     if ($savings) {
            //         self::addToAccountBalance($savings->id, $amount); // signed
            //     }
            //     return;
        }

        // Fallback: if we can't match, add signed to check as a safe default
        // self::addToFirstAccountOfType($checkTypeId, $amount);
        // return;


        // 3) Income: add signed amount to the check account (positive increments, negative decrements)
        if ($incomeTypeId && (int)$typeId === (int)$incomeTypeId) {
            self::addToFirstAccountOfType($checkTypeId, $amount);
            return;
        }

        // 4) Savings: no-op
        if ($savingsTypeId && (int)$typeId === (int)$savingsTypeId) {
            return;
        }

        // 5) Default: add signed amount to check account
        self::addToFirstAccountOfType($checkTypeId, $amount);
    }

    protected static function firstAccountOfType(?int $accountTypeId): ?Account
    {
        if (! $accountTypeId) return null;
        return Account::where('account_type_id', $accountTypeId)->orderBy('id')->first();
    }

    protected static function incrementFirstAccountOfType(?int $accountTypeId, float $amount): void
    {
        $account = self::firstAccountOfType($accountTypeId);
        if ($account) {
            self::incrementAccountBalance($account->id, $amount);
        }
    }

    protected static function decrementFirstAccountOfType(?int $accountTypeId, float $amount): void
    {
        $account = self::firstAccountOfType($accountTypeId);
        if ($account) {
            self::decrementAccountBalance($account->id, $amount);
        }
    }

    protected static function addToFirstAccountOfType(?int $accountTypeId, float $signedAmount): void
    {
        $account = self::firstAccountOfType($accountTypeId);
        if ($account) {
            self::addToAccountBalance($account->id, $signedAmount);
        }
    }

    protected static function incrementAccountBalance(?int $accountId, float $amount): void
    {
        if (! $accountId) return;
       $amt = floatval($amount);
        DB::table('accounts')->where('id', $accountId)->update([
            'balance' => DB::raw('COALESCE(balance,0) + ' . $amt),
        ]);

    }

    protected static function decrementAccountBalance(?int $accountId, float $amount): void
    {
        if (! $accountId) return;
        $amt = floatval($amount);
        DB::table('accounts')->where('id', $accountId)->update([
            'balance' => DB::raw('COALESCE(balance,0) - ' . $amt),
        ]);
    }

    protected static function addToAccountBalance(?int $accountId, float $signedAmount): void
    {
        if (! $accountId) return;
        $amt = floatval($signedAmount); // preserves sign
        // Note: concatenating negative amounts results in subtraction
        DB::table('accounts')->where('id', $accountId)->update([
            'balance' => DB::raw('COALESCE(balance,0) + ' . $amt),
        ]);
    }
}
