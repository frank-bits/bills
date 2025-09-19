<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Transaction;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Use sqlite in-memory by default per Laravel's testing config or the project's configuration
    }

    protected function makeAccount(string $name, int $typeId, float $balance = 1000.00): Account
    {
        $type = AccountType::factory()->create([
            'id' => $typeId,
            'name' => $name.' Type',
        ]);

        return Account::factory()->create([
            'name' => $name,
            'balance' => $balance,
            'account_type_id' => $type->id,
        ]);
    }

    public function test_credit_card_decrements_its_own_account()
    {
        $creditTypeId = 11;
        $credit = $this->makeAccount('Visa', $creditTypeId, 500.00);

        config()->set('balance.credit_card_type_id', $creditTypeId);
        config()->set('balance.check_account_type_id', 21);

        $tx = Transaction::create([
            'transaction' => 'CC Purchase',
            'amount' => 100.00,
            'date' => now()->toDateString(),
            'account_type_id' => $creditTypeId,
            'account_id' => $credit->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

        $this->assertEquals(400.00, (float) $credit->fresh()->balance);
    }

    public function test_income_increments_check_account()
    {
        $checkTypeId = 31;
        $incomeTypeId = 41;
        $check = $this->makeAccount('53 Check', $checkTypeId, 200.00);
        // ensure income account type exists for FK
        \App\Models\AccountType::factory()->create([
            'id' => $incomeTypeId,
            'name' => 'Income',
        ]);

        config()->set('balance.check_account_type_id', $checkTypeId);
        config()->set('balance.income_type_id', $incomeTypeId);

        $tx = Transaction::create([
            'transaction' => 'Paycheck',
            'amount' => 250.00,
            'date' => now()->toDateString(),
            'account_type_id' => $incomeTypeId,
            'account_id' => null,
        ]);

        BalanceService::applyForNewTransaction($tx);

        $this->assertEquals(450.00, (float) $check->fresh()->balance);
    }

    public function test_savings_is_noop()
    {
        $checkTypeId = 51;
        $savingsTypeId = 61;
        $check = $this->makeAccount('53 Check', $checkTypeId, 300.00);
        $savings = $this->makeAccount('53 Savings', $savingsTypeId, 800.00);

        config()->set('balance.check_account_type_id', $checkTypeId);
        config()->set('balance.savings_account_type_id', $savingsTypeId);

        $tx = Transaction::create([
            'transaction' => 'Transfer to Savings',
            'amount' => 75.00,
            'date' => now()->toDateString(),
            'account_type_id' => $savingsTypeId,
            'account_id' => $savings->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

        $this->assertEquals(300.00, (float) $check->fresh()->balance);
        $this->assertEquals(800.00, (float) $savings->fresh()->balance);
    }

    public function test_default_adds_signed_amount_to_check_account()
    {
        $checkTypeId = 71;
        $otherTypeId = 81;
        $check = $this->makeAccount('53 Check', $checkTypeId, 1000.00);
        $other = $this->makeAccount('Misc', $otherTypeId, 100.00);

        config()->set('balance.check_account_type_id', $checkTypeId);

    $tx = Transaction::create([
            'transaction' => 'Grocery',
            'amount' => 90.00,
            'date' => now()->toDateString(),
            'account_type_id' => $otherTypeId,
            'account_id' => $other->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

    // Signed-sum rule: positive adds to check
    $this->assertEquals(1090.00, (float) $check->fresh()->balance);
    }

    public function test_override_increments_target_account()
    {
        $overrideTypeId = 91;
        $checkTypeId = 92; // ensure check exists but shouldn't be used
        $check = $this->makeAccount('53 Check', $checkTypeId, 1000.00);
        $target = $this->makeAccount('Override Target', 93, 300.00);
        // ensure override type exists for FK
        \App\Models\AccountType::factory()->create([
            'id' => $overrideTypeId,
            'name' => 'Override',
        ]);

        config()->set('balance.check_account_type_id', $checkTypeId);
        config()->set('balance.increment_overrides', [
            $overrideTypeId => [$target->id],
        ]);

        $tx = Transaction::create([
            'transaction' => 'Special Deposit',
            'amount' => 40.00,
            'date' => now()->toDateString(),
            'account_type_id' => $overrideTypeId,
            'account_id' => $target->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

    $this->assertEquals(1000.00, (float) $check->fresh()->balance);
        $this->assertEquals(340.00, (float) $target->fresh()->balance);
    }

    public function test_transfer_to_savings_increases_savings_and_applies_signed_to_check()
    {
        $checkTypeId = 101;
        $savingsTypeId = 102;
        config()->set('balance.check_account_type_id', $checkTypeId);
        config()->set('balance.savings_account_type_id', $savingsTypeId);

        $check = $this->makeAccount('53 Check', $checkTypeId, 500.00);
        $savings = $this->makeAccount('53 Savings', $savingsTypeId, 200.00);

        // type 7 is transfer
        \App\Models\AccountType::factory()->create(['id' => 7, 'name' => 'transfer']);

        // Transfer -100 to savings: savings +100; check -100
        $tx = Transaction::create([
            'transaction' => 'Transfer',
            'amount' => -100.00,
            'date' => now()->toDateString(),
            'account_type_id' => 7,
            'account_id' => $savings->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

        $this->assertEquals(300.00, (float) $savings->fresh()->balance);
        $this->assertEquals(400.00, (float) $check->fresh()->balance);
    }

    public function test_transfer_to_check_increases_check_and_applies_signed_to_savings()
    {
        $checkTypeId = 111;
        $savingsTypeId = 112;
        config()->set('balance.check_account_type_id', $checkTypeId);
        config()->set('balance.savings_account_type_id', $savingsTypeId);

        $check = $this->makeAccount('53 Check', $checkTypeId, 500.00);
        $savings = $this->makeAccount('53 Savings', $savingsTypeId, 200.00);

        \App\Models\AccountType::factory()->create(['id' => 7, 'name' => 'transfer']);

        // Transfer +75 to check: check +75; savings +75
        $tx = Transaction::create([
            'transaction' => 'Transfer',
            'amount' => 75.00,
            'date' => now()->toDateString(),
            'account_type_id' => 7,
            'account_id' => $check->id,
        ]);

        BalanceService::applyForNewTransaction($tx);

        $this->assertEquals(575.00, (float) $check->fresh()->balance);
        $this->assertEquals(275.00, (float) $savings->fresh()->balance);
    }
}
