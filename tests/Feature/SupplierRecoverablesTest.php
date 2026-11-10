<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierAdvance;
use App\Models\SupplierBill;
use App\Models\User;
use App\Services\Accounting\SupplierRecoverables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierRecoverablesTest extends TestCase
{
    use RefreshDatabase;

    private User $finance;

    private Supplier $supplier;

    private ChartOfAccount $asset;

    private ChartOfAccount $bank;

    private ChartOfAccount $ap;

    private SupplierRecoverables $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(10, 0));
        $role = Role::create(['name' => 'Synthetic Finance', 'code' => 'FINANCE_MANAGER', 'status' => 'active', 'access_scope' => 'Company Level']);
        foreach (['view', 'approve', 'process'] as $action) {
            $role->permissions()->attach(Permission::firstOrCreate(['module' => 'Accounts Payable', 'action' => $action]));
        }
        foreach (['view', 'edit'] as $action) {
            $role->permissions()->attach(Permission::firstOrCreate(['module' => 'Suppliers', 'action' => $action]));
        }
        $this->finance = User::create(['name' => 'Test Finance', 'email' => 'advance-finance@example.test', 'password' => 'test-password', 'status' => 'active']);
        $this->finance->roles()->attach($role, ['is_primary' => true]);
        $this->asset = $this->account('1500', 'Supplier advances', 'asset');
        $this->bank = $this->account('1120', 'Bank', 'asset');
        $this->ap = $this->account('2100', 'Payables', 'liability');
        $this->supplier = Supplier::create(['name' => 'Synthetic supplier', 'code' => 'ADV-S', 'status' => 'active', 'allowed_payment_types' => 'Both']);
        AutomaticPostingRule::create(['source_module' => 'Supplier Advance', 'trigger_event' => 'Payment Recorded', 'debit_account_id' => $this->asset->id, 'auto_post' => true, 'approval_required' => false, 'status' => 'active', 'cost_center_rule' => 'None']);
        $this->service = app(SupplierRecoverables::class);
    }

    private function account(string $code, string $name, string $type): ChartOfAccount
    {
        return ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $type === 'asset' ? 'debit' : 'credit', 'status' => 'active']);
    }

    private function input(array $overrides = []): array
    {
        return $overrides + ['payment_date' => '2026-11-11', 'purpose' => 'advance', 'amount' => '100.00', 'reference' => 'Approved agreement A', 'reason' => 'Recoverable under contract A', 'recoverable_confirmed' => 1, 'payment_account_id' => $this->bank->id, 'idempotency_key' => (string) Str::uuid()];
    }

    private function bill(float $amount = 115): SupplierBill
    {
        $entry = JournalEntry::create(['journal_number' => 'BILL-'.Str::random(8), 'journal_date' => '2026-11-01', 'status' => 'posted', 'total_debit' => $amount, 'total_credit' => $amount]);
        $entry->lines()->create(['chart_of_account_id' => $this->ap->id, 'debit' => 0, 'credit' => $amount]);

        return SupplierBill::create(['supplier_id' => $this->supplier->id, 'bill_number' => 'SB-'.Str::random(8), 'bill_date' => '2026-11-01', 'taxable_amount' => 100, 'vat_amount' => $amount - 100, 'vat_rate' => 15, 'total_amount' => $amount, 'balance_amount' => $amount, 'status' => 'unpaid', 'journal_entry_id' => $entry->id]);
    }

    private function movement(array $overrides = []): array
    {
        return $overrides + ['movement_date' => '2026-11-11', 'amount' => '40.00', 'reference' => 'Approved settlement A', 'idempotency_key' => (string) Str::uuid()];
    }

    public function test_advance_posts_dedicated_asset_and_bank_without_vat_or_bill_changes(): void
    {
        $bill = $this->bill();
        $before = $bill->fresh()->getAttributes();
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->assertSame(100.0, $advance->available());
        $this->assertSame('posted', $advance->journalEntry->status);
        $this->assertSame(100.0, $this->asset->postedBalance());
        $this->assertSame(-100.0, $this->bank->postedBalance());
        $this->assertSame($before, $bill->fresh()->getAttributes());
        $this->assertDatabaseCount('vat_transactions', 0);
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertTrue($advance->journalEntry->isApprovalControlledSource());
    }

    public function test_application_uses_original_ap_account_and_reconciles_bill_and_asset(): void
    {
        $bill = $this->bill();
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $other = $this->account('2101', 'New linked AP', 'liability');
        $this->supplier->update(['linked_account_id' => $other->id]);
        $movement = $this->service->settle($advance, 'apply', $this->movement(['supplier_bill_id' => $bill->id]), $this->finance);
        $this->assertSame(75.0, (float) $bill->fresh()->balance_amount);
        $this->assertSame(40.0, (float) $bill->fresh()->paid_amount);
        $this->assertSame(60.0, $advance->fresh()->available());
        $this->assertSame(60.0, $this->asset->postedBalance());
        $this->assertSame(75.0, $this->ap->postedBalance());
        $this->assertSame(0.0, $other->postedBalance());
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('vat_transactions', 0);
        $this->assertTrue($movement->journalEntry->isApprovalControlledSource());
    }

    public function test_grn_price_variance_credit_does_not_hide_the_original_payable_account(): void
    {
        $bill = $this->bill();
        $expense = $this->account('5200', 'Purchase variance', 'expense');
        $bill->journalEntry->lines()->create(['chart_of_account_id' => $expense->id, 'debit' => 0, 'credit' => 5]);
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->service->settle($advance, 'apply', $this->movement(['supplier_bill_id' => $bill->id]), $this->finance);
        $this->assertSame(75.0, (float) $bill->fresh()->balance_amount);
        $this->assertSame(75.0, $this->ap->postedBalance());
    }

    public function test_missing_reason_or_nonmatching_original_request_is_rejected(): void
    {
        $data = $this->input();
        $advance = $this->service->record($this->supplier, $data, $this->finance);
        foreach ([['amount' => 101], ['reason' => 'Changed contract text'], ['reference' => 'Changed reference']] as $changed) {
            try {
                $this->service->record($this->supplier, array_replace($data, $changed), $this->finance);
                $this->fail('Changed request accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('idempotency_key', $e->errors());
            }
        }
        $this->expectException(ValidationException::class);
        $this->service->reverse($advance, null, '', $this->finance);
    }

    public function test_partial_refund_application_and_reversals_preserve_rows_and_balances(): void
    {
        $bill = $this->bill();
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $apply = $this->service->settle($advance, 'apply', $this->movement(['supplier_bill_id' => $bill->id]), $this->finance);
        $refund = $this->service->settle($advance, 'refund', $this->movement(['amount' => 20, 'payment_account_id' => $this->bank->id]), $this->finance);
        $this->assertSame(40.0, $advance->fresh()->available());
        $this->assertSame(40.0, $this->asset->postedBalance());
        $this->assertSame(-80.0, $this->bank->postedBalance());
        $this->service->reverse($advance, $refund, 'Refund was entered twice', $this->finance);
        $this->service->reverse($advance, $refund, 'Retry same correction', $this->finance);
        $this->service->reverse($advance, $apply, 'Wrong bill selected', $this->finance);
        $this->assertSame(115.0, (float) $bill->fresh()->balance_amount);
        $this->service->reverse($advance, null, 'Payment cancelled by Finance', $this->finance);
        $this->assertSame(0.0, $this->asset->postedBalance());
        $this->assertSame(0.0, $this->bank->postedBalance());
        $this->assertSame(0.0, $advance->fresh()->available());
        $this->assertDatabaseCount('supplier_advances', 1);
        $this->assertDatabaseCount('supplier_advance_movements', 2);
        $this->assertDatabaseCount('journal_entries', 7);
    }

    public function test_retries_do_not_duplicate_and_changed_payload_is_refused(): void
    {
        $data = $this->input();
        $advance = $this->service->record($this->supplier, $data, $this->finance);
        $this->assertSame($advance->id, $this->service->record($this->supplier, $data, $this->finance)->id);
        $payload = $this->movement(['payment_account_id' => $this->bank->id]);
        $m = $this->service->settle($advance, 'refund', $payload, $this->finance);
        $this->assertSame($m->id, $this->service->settle($advance, 'refund', $payload, $this->finance)->id);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->expectException(ValidationException::class);
        $this->service->settle($advance, 'refund', array_replace($payload, ['amount' => 41]), $this->finance);
    }

    public function test_mapping_is_required_and_cash_or_tax_control_cannot_be_advance_asset(): void
    {
        AutomaticPostingRule::query()->update(['debit_account_id' => $this->bank->id]);
        $this->expectException(ValidationException::class);
        try {
            $this->service->record($this->supplier, $this->input(), $this->finance);
        } finally {
            $this->assertDatabaseCount('supplier_advances', 0);
            $this->assertDatabaseCount('journal_entries', 0);
        }
    }

    public function test_missing_recoverability_confirmation_or_reason_and_forged_precision_are_refused(): void
    {
        foreach ([['recoverable_confirmed' => 0], ['reason' => ''], ['amount' => '1.001'], ['purpose' => 'taxable_back_charge']] as $bad) {
            try {
                $this->service->record($this->supplier, $this->input($bad), $this->finance);
                $this->fail('Invalid payment accepted');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_over_application_or_refund_rolls_back_everything(): void
    {
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->expectException(ValidationException::class);
        try {
            $this->service->settle($advance, 'refund', $this->movement(['amount' => 101, 'payment_account_id' => $this->bank->id]), $this->finance);
        } finally {
            $this->assertDatabaseCount('supplier_advance_movements', 0);
            $this->assertDatabaseCount('journal_entries', 1);
        }
    }

    public function test_different_supplier_bill_cannot_be_used(): void
    {
        $bill = $this->bill();
        $bill->update(['supplier_id' => Supplier::create(['name' => 'Other', 'code' => 'ADV-O', 'status' => 'active'])->id]);
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->expectException(ValidationException::class);
        $this->service->settle($advance, 'apply', $this->movement(['supplier_bill_id' => $bill->id]), $this->finance);
    }

    public function test_supplier_payment_channel_restriction_is_enforced(): void
    {
        $this->supplier->update(['allowed_payment_types' => 'Cash']);
        $this->expectException(ValidationException::class);
        $this->service->record($this->supplier, $this->input(), $this->finance);
    }

    public function test_salary_and_repair_on_behalf_are_recoverables_not_payroll_or_expense(): void
    {
        foreach (['salary_on_behalf', 'repair_on_behalf'] as $purpose) {
            $this->service->record($this->supplier, $this->input(['purpose' => $purpose]), $this->finance);
        }
        $this->assertSame(200.0, $this->asset->postedBalance());
        $this->assertDatabaseCount('payroll_settlements', 0);
        $this->assertDatabaseCount('supplier_bills', 0);
        $this->assertDatabaseCount('vat_transactions', 0);
    }

    public function test_original_cannot_be_reversed_with_active_movements(): void
    {
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->service->settle($advance, 'refund', $this->movement(['payment_account_id' => $this->bank->id]), $this->finance);
        $this->expectException(ValidationException::class);
        $this->service->reverse($advance, null, 'Cancel original payment', $this->finance);
    }

    public function test_http_forbidden_without_finance_approval_and_payment_permissions(): void
    {
        $viewer = User::create(['name' => 'Viewer', 'email' => 'advance-viewer@example.test', 'password' => 'test-password', 'status' => 'active']);
        $this->actingAs($viewer)->post(route('admin.accounting.accounts-payable.advances.store', $this->supplier), $this->input())->assertForbidden();
        $this->assertDatabaseCount('supplier_advances', 0);
    }

    public function test_http_record_ignores_forged_accounts_and_journal_fields_and_replays_once(): void
    {
        $data = $this->input() + ['asset_account_id' => $this->bank->id, 'journal_entry_id' => 999, 'reversed_at' => now()->toDateTimeString()];
        $url = route('admin.accounting.accounts-payable.advances.store', $this->supplier);
        $this->actingAs($this->finance)->postJson($url, $data)->assertOk()->assertJsonStructure(['message', 'panel_url']);
        $this->postJson($url, $data)->assertOk();
        $advance = SupplierAdvance::sole();
        $this->assertSame($this->asset->id, $advance->asset_account_id);
        $this->assertNotSame(999, $advance->journal_entry_id);
        $this->assertNull($advance->reversed_at);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_route_supplier_cannot_be_forged_and_bill_payment_cannot_create_on_behalf_recovery(): void
    {
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $other = Supplier::create(['name' => 'Other supplier', 'code' => 'ADV-OTHER', 'status' => 'active']);
        $this->actingAs($this->finance)->postJson(route('admin.accounting.accounts-payable.advances.settle', [$other, $advance, 'refund']), $this->movement(['payment_account_id' => $this->bank->id]))->assertNotFound();
        $bill = $this->bill();
        $this->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => '2026-11-11', 'amount' => 10, 'payment_account_id' => $this->bank->id,
            'purpose' => "Salary paid on supplier's behalf", 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('purpose');
        $this->assertDatabaseCount('supplier_payments', 0);
        $this->assertDatabaseCount('supplier_advance_movements', 0);
    }

    public function test_cash_payment_after_advance_application_reconciles_without_counting_advance_as_cash(): void
    {
        $bill = $this->bill();
        $advance = $this->service->record($this->supplier, $this->input(), $this->finance);
        $this->service->settle($advance, 'apply', $this->movement(['supplier_bill_id' => $bill->id]), $this->finance);
        AutomaticPostingRule::create(['source_module' => 'Supplier Payment', 'trigger_event' => 'Payment Recorded', 'auto_post' => true, 'status' => 'active', 'cost_center_rule' => 'None']);
        $this->actingAs($this->finance)->post(route('admin.accounting.accounts-payable.payment.store', $bill), [
            'payment_date' => '2026-11-11', 'amount' => 25, 'payment_account_id' => $this->bank->id, 'purpose' => 'Bill payment', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(50.0, (float) $bill->fresh()->balance_amount);
        $this->assertSame(25.0, (float) $bill->payments()->sum('amount'));
        $this->assertSame(40.0, (float) $bill->advanceApplications()->sum('amount'));
        $this->assertSame(50.0, $this->ap->postedBalance());
        $this->assertDatabaseCount('vat_transactions', 0);
    }

    public function test_project_scope_prevents_foreign_record_actions_and_visibility(): void
    {
        $project = Project::create(['name' => 'A', 'code' => 'ADV-A', 'status' => 'active']);
        $other = Project::create(['name' => 'B', 'code' => 'ADV-B', 'status' => 'active']);
        $advance = $this->service->record($this->supplier, $this->input(['project_id' => $other->id]), $this->finance);
        $this->finance->roles()->firstOrFail()->update(['access_scope' => 'Project Level']);
        $this->finance->update(['project_id' => $project->id]);
        $actor = $this->finance->fresh();
        $this->actingAs($actor)->post(route('admin.accounting.accounts-payable.advances.settle', [$this->supplier, $advance, 'refund']), $this->movement(['payment_account_id' => $this->bank->id]))->assertNotFound();
        $this->assertFalse($this->service->scoped($actor)->exists());
        $this->assertDatabaseCount('supplier_advance_movements', 0);
    }

    public function test_supplier_view_is_read_only_and_edit_panel_has_connected_actions_in_both_languages(): void
    {
        $this->service->record($this->supplier, $this->input(), $this->finance);
        foreach (['en', 'ar'] as $locale) {
            $this->finance->update(['language' => $locale]);
            $this->actingAs($this->finance->fresh())->get(route('admin.master.suppliers.show', $this->supplier))->assertOk()->assertDontSee('name="recoverable_confirmed"', false);
            $this->getJson(route('admin.master.suppliers.workspace.panel', [$this->supplier, 'advances']))->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, 'recoverable_confirmed') && ! str_contains($html, 'supplier_advances.'));
        }
    }
}
