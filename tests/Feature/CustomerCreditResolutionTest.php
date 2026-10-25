<?php

namespace Tests\Feature;

use App\Models\AutomaticPostingRule;
use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerCreditAllocation;
use App\Models\CustomerCreditRefund;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\VatTransaction;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Accounting\CustomerCreditResolutionService;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Zatca\ZatcaSubmissionService;
use App\Support\AgeingBuckets;
use App\Support\Workspace\CustomerWorkspacePanels;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\OnboardsZatcaUnit;
use Tests\TestCase;

/**
 * Customer credit resolution: the unapplied credit of a posted credit note is
 * applied to another open invoice of the same customer (sub-ledger only) or
 * refunded from bank / cash (Dr AR / Cr bank), partially and repeatedly,
 * with reversals; no revenue, VAT or ZATCA effect; AR stays reconciled.
 */
class CustomerCreditResolutionTest extends TestCase
{
    use OnboardsZatcaUnit;
    use RefreshDatabase;

    private Project $project;

    private Project $otherProject;

    private Customer $customer;

    private Customer $otherCustomer;

    private ZatcaEgsUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        config(['zatca.environment' => 'sandbox', 'zatca.production_enabled' => false, 'zatca.endpoints.sandbox' => self::SANDBOX]);
        CompanyProfile::query()->update(['name' => 'Seera Construction Co.', 'name_ar' => 'شركة سيرا للإنشاءات', 'vat_number' => '300123456700003', 'cr_number' => '1010123456',
            'street' => 'King Fahd Road', 'building_number' => '7890', 'district' => 'Al Olaya', 'city' => 'Riyadh', 'postal_code' => '12211', 'country_code' => 'SA', 'seller_id_scheme' => 'CRN']);
        $this->project = Project::create(['name' => 'Credit Tower', 'code' => 'PRJ-CRED', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Other Site', 'code' => 'PRJ-CRED-O', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-CRED', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
        $this->otherCustomer = Customer::create(['name' => 'Jeddah Builders', 'code' => 'CUS-CRED-O', 'type' => 'Company', 'vat_number' => '300111222300003', 'street' => 'Corniche', 'building_number' => '4321',
            'district' => 'Al Hamra', 'city' => 'Jeddah', 'postal_code' => '21411', 'country_code' => 'SA', 'status' => 'active']);
        $this->unit = $this->onboardedUnit();
    }

    // ---------------------------------------------------------------- helpers

    /** Posted standard invoice of 2,000 + 300 VAT = 2,300 (10 × 100 + 5 × 200). */
    private function invoice(?Customer $customer = null, ?Project $project = null, ?array $lines = null): CustomerInvoice
    {
        $lines ??= [['description' => 'Concrete works', 'quantity' => 10, 'unit_price' => 100], ['description' => 'Steel works', 'quantity' => 5, 'unit_price' => 200]];
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), [
            'customer_id' => ($customer ?? $this->customer)->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => ($project ?? $this->project)->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'invoice_type' => 'standard', 'lines' => $lines,
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        $this->flushSession();

        return $invoice->fresh(['lines', 'customer']);
    }

    private function receipt(CustomerInvoice $invoice, float $amount): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => now()->toDateString(), 'receipt_account_id' => $this->account('1120')->id, 'payment_method' => 'Bank Transfer', 'amount' => $amount, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->flushSession();
    }

    /** A paid invoice, then a credit note of 2 × 100 (230 incl. VAT) whose whole value is unapplied customer credit. */
    private function creditNote(float $units = 2, ?CustomerInvoice $invoice = null): CustomerAdjustmentNote
    {
        $invoice ??= $this->invoice();
        if ((float) $invoice->fresh()->balance_amount > 0) {
            $this->receipt($invoice, (float) $invoice->fresh()->balance_amount);
        }
        $note = app(CustomerNoteService::class)->createDraft($invoice, 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'Returned goods'],
            [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => $units, 'unit_price' => 100]], $this->admin());

        return app(CustomerNoteService::class)->post($note, $this->admin());
    }

    private function credits(): CustomerCreditResolutionService
    {
        return app(CustomerCreditResolutionService::class);
    }

    private function account(string $code): ChartOfAccount
    {
        return ChartOfAccount::where('account_code', $code)->firstOrFail();
    }

    private function glBalance(string $code): float
    {
        $lines = JournalEntryLine::withoutGlobalScopes()->where('chart_of_account_id', $this->account($code)->id)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->get();

        return round((float) $lines->sum('debit') - (float) $lines->sum('credit'), 2);
    }

    /** GL AR = open invoice balances − available customer credit (credit balances sit in AR). */
    private function assertArReconciled(string $when): void
    {
        $open = round((float) CustomerInvoice::withoutGlobalScopes()->whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_amount'), 2);
        $credit = 0.0;
        foreach (CustomerAdjustmentNote::withoutGlobalScopes()->where('note_type', 'credit')->where('status', 'posted')->get() as $note) {
            $credit += $this->credits()->availableCredit($note);
        }
        $this->assertSame($this->glBalance('1200'), round($open - $credit, 2), 'AR reconciliation failed '.$when);
    }

    private function allocate(CustomerAdjustmentNote $note, CustomerInvoice $target, float $amount, array $extra = []): CustomerCreditAllocation
    {
        return $this->credits()->allocate($note, $target, $extra + ['amount' => $amount, 'allocation_date' => now()->toDateString()], $this->admin())[0];
    }

    private function refund(CustomerAdjustmentNote $note, float $amount, string $code = '1120', array $extra = []): CustomerCreditRefund
    {
        return $this->credits()->refund($note, $extra + ['amount' => $amount, 'payment_date' => now()->toDateString(), 'payment_account_id' => $this->account($code)->id], $this->admin())[0];
    }

    public function test_review_mode_refunds_roll_back_and_retry_without_consuming_credit(): void
    {
        $note = $this->creditNote();
        $key = (string) Str::uuid();
        $journals = JournalEntry::count();
        $rule = AutomaticPostingRule::where('source_module', 'Customer Receipt')->firstOrFail();
        $rule->update(['auto_post' => false]);
        foreach ([false, true] as $ownRule) {
            if ($ownRule) {
                AutomaticPostingRule::create(['source_module' => 'Customer Credit Refund', 'trigger_event' => 'Refund Recorded', 'auto_post' => false, 'status' => 'active']);
                $rule->update(['auto_post' => true]);
            }
            try {
                $this->refund($note, 100, '1120', ['idempotency_key' => $key]);
                $this->fail('Draft journal must not finalize a refund');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('payment_account_id', $e->errors());
            }
            $this->assertSame(230.0, $this->credits()->availableCredit($note));
            $this->assertSame(0, CustomerCreditRefund::count());
            $this->assertSame($journals, JournalEntry::count());
            $this->assertArReconciled('after refused review-mode refund');
        }
        AutomaticPostingRule::where('source_module', 'Customer Credit Refund')->update(['auto_post' => true]);
        $refund = $this->refund($note, 100, '1120', ['idempotency_key' => $key]);
        $this->assertSame('posted', $refund->journalEntry->status);
        $this->assertSame(130.0, $this->credits()->availableCredit($note));
        $this->assertSame($refund->id, $this->refund($note, 100, '1120', ['idempotency_key' => $key])->id);
        $this->credits()->reverseRefund($refund, 'Test reversal', $this->admin());
        $this->assertSame(230.0, $this->credits()->availableCredit($note));
        $this->assertArReconciled('after review-mode retry and reversal');
    }

    public function test_legacy_draft_refund_cannot_be_replayed_or_reversed_as_a_posted_payment(): void
    {
        $note = $this->creditNote();
        $key = (string) Str::uuid();
        $refund = $this->refund($note, 100, '1120', ['idempotency_key' => $key]);
        $refund->journalEntry->update(['status' => 'draft']);
        $journals = JournalEntry::count();
        foreach (['replay', 'reverse'] as $operation) {
            try {
                if ($operation === 'replay') {
                    $this->refund($note, 100, '1120', ['idempotency_key' => $key]);
                } else {
                    $this->credits()->reverseRefund($refund, 'Legacy inconsistency', $this->admin());
                }
                $this->fail('Legacy draft refund must require Finance review');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame($journals, JournalEntry::count());
        $this->assertFalse($refund->fresh()->isReversed());
        $this->assertSame(130.0, $this->credits()->availableCredit($note));
    }

    public function test_credit_resolution_requires_the_source_journal_to_be_posted(): void
    {
        $note = $this->creditNote();
        $target = $this->invoice();
        $note->journalEntry->update(['status' => 'draft']); // legacy review-mode source
        foreach (['allocate', 'refund'] as $action) {
            try {
                $action === 'allocate' ? $this->allocate($note, $target, 100) : $this->refund($note, 100);
                $this->fail('Unposted source must not be spent');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('note', $e->errors());
            }
        }
        $this->assertSame(0, CustomerCreditAllocation::count());
        $this->assertSame(0, CustomerCreditRefund::count());
    }

    public function test_credit_register_filters_before_pagination_and_totals_all_scoped_matches(): void
    {
        $first = $this->creditNote();
        // Register-only fixtures: 16 more posted credit documents sharing the test masters.
        for ($i = 0; $i < 16; $i++) {
            $copy = $first->replicate();
            $copy->note_number = 'CN-PAGE-'.$i;
            $copy->save();
        }
        $this->refund($first, 230); // only oldest row is fully used, beyond page 1 without SQL filtering
        $response = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.credits.index', ['status' => 'fully_used']))->assertOk();
        $this->assertSame(1, $response->viewData('notes')->total());
        $this->assertSame($first->id, $response->viewData('rows')->first()['note']->id);
        $this->assertSame(230.0, $response->viewData('totals')['refunded']);
        $response = $this->get(route('admin.accounting.accounts-receivable.credits.index', ['status' => 'available']))->assertOk();
        $this->assertSame(16, $response->viewData('notes')->total());
        $this->assertSame(3680.0, $response->viewData('totals')['available']);
        $response = $this->get(route('admin.accounting.accounts-receivable.credits.index', ['status' => 'available', 'page' => 2]))->assertOk();
        $this->assertCount(1, $response->viewData('rows'));
        $this->assertSame(3680.0, $response->viewData('totals')['available']);
        $other = $this->user(['Accounts Receivable' => ['view']], $this->otherProject);
        $response = $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.credits.index'))->assertOk();
        $this->assertSame(0, $response->viewData('notes')->total());
        $this->assertSame(0.0, $response->viewData('totals')['available']);
    }

    // --------------------------------------------------------- available credit (1-3)

    public function test_available_credit_comes_only_from_posted_credit_notes_with_unapplied_value(): void
    {
        $note = $this->creditNote(); // 230 unapplied
        $this->assertSame(230.0, $this->credits()->availableCredit($note));
        $this->assertSame(['credit' => 230.0, 'applied' => 0.0, 'refunded' => 0.0, 'available' => 230.0, 'status' => 'available'], $this->credits()->summary($note));
        // A credit note fully applied to its own invoice has no customer credit.
        $open = $this->invoice();
        $applied = app(CustomerNoteService::class)->post(app(CustomerNoteService::class)->createDraft($open, 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'x'], [['invoice_line_id' => $open->lines[0]->id, 'quantity' => 1, 'unit_price' => 100]], $this->admin()), $this->admin());
        $this->assertSame(0.0, $this->credits()->availableCredit($applied));
        $this->assertFalse($applied->hasUnappliedCredit());
        // A draft credit note and a debit note never resolve.
        $draft = app(CustomerNoteService::class)->createDraft($open, 'credit', ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'x'], [['invoice_line_id' => $open->lines[1]->id, 'quantity' => 1, 'unit_price' => 200]], $this->admin());
        $this->assertSame(0.0, $this->credits()->availableCredit($draft));
        foreach ([$draft, $applied] as $source) {
            try {
                $this->allocate($source, $this->invoice(), 10);
                $this->fail('resolved a non-source');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($source->isDraft() ? 'note' : 'amount', $e->errors());
            }
            try {
                $this->refund($source, 10);
                $this->fail('refunded a non-source');
            } catch (ValidationException $e) {
                $this->assertTrue(isset($e->errors()['note']) || isset($e->errors()['amount']));
            }
        }
        $this->assertSame(230.0, $this->credits()->customerAvailableCredit($this->customer));
    }

    // ------------------------------------------------------------ allocation (4-19)

    public function test_allocation_eligibility_same_customer_open_posted_target_and_server_amounts(): void
    {
        $note = $this->creditNote(); // 230
        $foreign = $this->invoice($this->otherCustomer);
        try {
            $this->allocate($note, $foreign, 100);
            $this->fail('cross-customer');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_invoice_id', $e->errors());
        }
        $draft = CustomerInvoice::create(['customer_id' => $this->customer->id, 'invoice_number' => 'INV-DRAFT-X', 'invoice_date' => now()->toDateString(), 'project_id' => $this->project->id, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115, 'balance_amount' => 115, 'payment_status' => 'draft']);
        try {
            $this->allocate($note, $draft, 100);
            $this->fail('draft target');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_invoice_id', $e->errors());
        }
        $paid = $this->invoice();
        $this->receipt($paid, 2300);
        try {
            $this->allocate($note, $paid, 100);
            $this->fail('paid target');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_invoice_id', $e->errors());
        }
        try {
            $this->allocate($note, $note->invoice, 100);
            $this->fail('original invoice');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_invoice_id', $e->errors());
        }
        $target = $this->invoice();
        foreach ([0, -5] as $bad) {
            try {
                $this->allocate($note, $target, $bad);
                $this->fail('non-positive');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('amount', $e->errors());
            }
        }
        try {
            $this->allocate($note, $target, 230.01);
            $this->fail('exceeds credit');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('230.00', $e->errors()['amount'][0]);
        }
        $small = $this->invoice(lines: [['description' => 'Small', 'quantity' => 1, 'unit_price' => 100]]); // 115 open
        try {
            $this->allocate($note, $small, 115.01);
            $this->fail('exceeds outstanding');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('115.00', $e->errors()['amount'][0]);
        }
        // HTTP: a forged customer id is ignored, a foreign invoice id is a 404 under scope, the valid request works.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), ['customer_invoice_id' => $foreign->id, 'customer_id' => $this->otherCustomer->id, 'amount' => 50, 'allocation_date' => now()->toDateString()])->assertSessionHasErrors('customer_invoice_id');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), ['customer_invoice_id' => $target->id, 'amount' => 50, 'allocation_date' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(180.0, $this->credits()->availableCredit($note));
    }

    public function test_partial_and_multiple_allocations_reduce_targets_without_gl_vat_or_zatca_effect(): void
    {
        $note = $this->creditNote(5); // 575 unapplied
        $b = $this->invoice(); // 2,300 open
        $c = $this->invoice(lines: [['description' => 'C', 'quantity' => 1, 'unit_price' => 200]]); // 230 open
        $gl = ['ar' => $this->glBalance('1200'), 'rev' => $this->glBalance('4100'), 'vat' => $this->glBalance('2210'), 'bank' => $this->glBalance('1120'), 'cash' => $this->glBalance('1110')];
        $journals = JournalEntry::count();
        $vat = VatTransaction::count();
        $records = ZatcaInvoiceRecord::count();
        $this->assertArReconciled('before allocation');

        $this->allocate($note, $b, 200);
        $this->allocate($note, $c, 230);
        $this->assertSame(145.0, $this->credits()->availableCredit($note));
        $this->assertSame('2100.00', $b->fresh()->balance_amount);
        $this->assertSame('partially_paid', $b->fresh()->payment_status);
        $this->assertSame('0.00', $b->fresh()->received_amount, 'credit applied is not cash received');
        $this->assertSame(200.0, $b->fresh()->creditAllocatedAmount());
        $this->assertSame('0.00', $c->fresh()->balance_amount);
        $this->assertSame('paid', $c->fresh()->payment_status);
        $this->assertSame('2300.00', $b->fresh()->total_amount, 'target total untouched');

        $this->assertSame($gl, ['ar' => $this->glBalance('1200'), 'rev' => $this->glBalance('4100'), 'vat' => $this->glBalance('2210'), 'bank' => $this->glBalance('1120'), 'cash' => $this->glBalance('1110')], 'allocation moves no GL');
        $this->assertSame($journals, JournalEntry::count());
        $this->assertSame($vat, VatTransaction::count());
        $this->assertSame($records, ZatcaInvoiceRecord::count());
        $this->assertSame($this->credits()->summary($note)['status'], 'partially_used');
        $this->assertArReconciled('after allocations');
        // Exhaust the credit.
        $d = $this->invoice();
        $this->allocate($note, $d, 145);
        $this->assertSame(0.0, $this->credits()->availableCredit($note));
        $this->assertSame('fully_used', $this->credits()->summary($note)['status']);
        try {
            $this->allocate($note, $d, 1);
            $this->fail('exhausted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    public function test_exact_allocation_retry_is_replayed_and_a_conflicting_reuse_is_refused(): void
    {
        $note = $this->creditNote(); // 230
        $target = $this->invoice();
        $key = (string) Str::uuid();
        $first = $this->allocate($note, $target, 100, ['idempotency_key' => $key]);
        [$replay, $replayed] = $this->credits()->allocate($note, $target, ['amount' => 100, 'allocation_date' => now()->toDateString(), 'idempotency_key' => $key], $this->admin());
        $this->assertTrue($replayed);
        $this->assertSame($first->id, $replay->id);
        $this->assertSame(1, CustomerCreditAllocation::count());
        $this->assertSame(130.0, $this->credits()->availableCredit($note));
        try {
            $this->credits()->allocate($note, $target, ['amount' => 120, 'allocation_date' => now()->toDateString(), 'idempotency_key' => $key], $this->admin());
            $this->fail('conflicting reuse');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }
    }

    public function test_allocation_reversal_restores_credit_and_target_balance_and_keeps_the_row(): void
    {
        $note = $this->creditNote(); // 230
        $target = $this->invoice();
        $allocation = $this->allocate($note, $target, 230);
        $this->assertSame('2070.00', $target->fresh()->balance_amount);
        $this->assertSame(0.0, $this->credits()->availableCredit($note));
        $reversed = $this->credits()->reverseAllocation($allocation, 'Applied to the wrong invoice', $this->admin());
        $this->assertNotNull($reversed->reversed_at);
        $this->assertSame('Applied to the wrong invoice', $reversed->reversal_reason);
        $this->assertSame(1, CustomerCreditAllocation::count(), 'row kept');
        $this->assertSame('2300.00', $target->fresh()->balance_amount);
        $this->assertSame('unpaid', $target->fresh()->payment_status);
        $this->assertSame(230.0, $this->credits()->availableCredit($note));
        $this->assertArReconciled('after allocation reversal');
        // Reversing again changes nothing; the credit can be applied anew.
        $this->credits()->reverseAllocation($allocation->fresh(), 'again', $this->admin());
        $this->assertSame('Applied to the wrong invoice', $allocation->fresh()->reversal_reason);
        $this->allocate($note, $target, 230);
        $this->assertSame('2070.00', $target->fresh()->balance_amount);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Customer credit allocation reversed']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Customer credit applied to invoice']);
    }

    // --------------------------------------------------------------- refund (20-35)

    public function test_refund_posts_dr_ar_cr_bank_or_cash_without_revenue_vat_or_zatca(): void
    {
        $note = $this->creditNote(); // 230
        $before = ['ar' => $this->glBalance('1200'), 'rev' => $this->glBalance('4100'), 'vat' => $this->glBalance('2210'), 'bank' => $this->glBalance('1120'), 'cash' => $this->glBalance('1110')];
        $vatRows = VatTransaction::count();
        $records = ZatcaInvoiceRecord::count();
        $noteJournal = $note->journalEntry->fresh();
        $this->assertArReconciled('before refund');

        $bank = $this->refund($note, 150, '1120');
        $this->assertSame('Customer Credit Refund', $bank->journalEntry->source_module);
        $this->assertSame($bank->id, (int) $bank->journalEntry->source_id);
        $this->assertSame($note->note_number, $bank->journalEntry->reference_number);
        $this->assertSame('posted', $bank->journalEntry->status);
        $this->assertSame('Bank Transfer', $bank->payment_method);
        $this->assertSame($before['ar'] + 150, $this->glBalance('1200'));
        $this->assertSame($before['bank'] - 150, $this->glBalance('1120'));
        $cash = $this->refund($note, 80, '1110');
        $this->assertSame('Cash', $cash->payment_method);
        $this->assertSame($before['cash'] - 80, $this->glBalance('1110'));
        $this->assertSame($before['ar'] + 230, $this->glBalance('1200'));
        $this->assertSame($before['rev'], $this->glBalance('4100'), 'no revenue');
        $this->assertSame($before['vat'], $this->glBalance('2210'), 'no VAT account movement');
        $this->assertSame($vatRows, VatTransaction::count(), 'no VAT row');
        $this->assertSame(0, VatTransaction::where('source_module', 'Customer Credit Refund')->count());
        $this->assertSame($records, ZatcaInvoiceRecord::count(), 'no ZATCA record');
        $this->assertSame(0.0, $this->credits()->availableCredit($note));
        $this->assertSame(['credit' => 230.0, 'applied' => 0.0, 'refunded' => 230.0, 'available' => 0.0, 'status' => 'fully_used'], $this->credits()->summary($note));
        $this->assertArReconciled('after refunds');
        // Originals untouched.
        $this->assertSame($noteJournal->total_debit, $note->journalEntry->fresh()->total_debit);
        $this->assertSame('230.00', $note->fresh()->total_amount);
        $this->assertSame('230.00', $note->fresh()->unapplied_amount);
        $this->assertSame(1, VatTransaction::where('source_module', 'Customer Credit Note')->where('source_id', $note->id)->where('vat_amount', -30)->count());
        $this->assertSame('2300.00', $note->invoice->fresh()->received_amount, 'original receipt kept');
        try {
            $this->refund($note, 1);
            $this->fail('exhausted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $this->assertDatabaseHas('activity_logs', ['action' => 'Customer refund recorded']);
    }

    public function test_refund_refuses_wrong_or_inactive_accounts_and_a_channel_the_customer_does_not_accept(): void
    {
        $note = $this->creditNote();
        $revenue = $this->account('4100');
        foreach ([$revenue->id, 999999] as $bad) {
            try {
                $this->credits()->refund($note, ['amount' => 10, 'payment_date' => now()->toDateString(), 'payment_account_id' => $bad], $this->admin());
                $this->fail('bad account');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('payment_account_id', $e->errors());
            }
        }
        $cash = $this->account('1110');
        $cash->update(['status' => 'inactive']);
        try {
            $this->refund($note, 10, '1110');
            $this->fail('inactive');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_account_id', $e->errors());
        }
        $cash->update(['status' => 'active']);
        $this->customer->update(['allowed_payment_types' => 'Bank']);
        try {
            $this->refund($note, 10, '1110');
            $this->fail('channel');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_account_id', $e->errors());
        }
        try {
            $this->credits()->refund($note, ['amount' => 10, 'payment_date' => now()->toDateString(), 'payment_account_id' => $this->account('1120')->id, 'payment_method' => 'Cash'], $this->admin());
            $this->fail('method mismatch');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payment_method', $e->errors());
        }
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.refund', $note), ['payment_account_id' => $revenue->id, 'amount' => 10, 'payment_date' => now()->toDateString()])->assertSessionHasErrors('payment_account_id');
        $this->assertSame(0, CustomerCreditRefund::count());
    }

    public function test_refund_idempotency_replays_exact_retries_and_refuses_conflicts(): void
    {
        $note = $this->creditNote();
        $key = (string) Str::uuid();
        $first = $this->refund($note, 100, '1120', ['idempotency_key' => $key]);
        [$again, $replayed] = $this->credits()->refund($note, ['amount' => 100, 'payment_date' => now()->toDateString(), 'payment_account_id' => $this->account('1120')->id, 'idempotency_key' => $key], $this->admin());
        $this->assertTrue($replayed);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, CustomerCreditRefund::count());
        $this->assertSame(1, JournalEntry::where('source_module', 'Customer Credit Refund')->count());
        $this->assertSame(130.0, $this->credits()->availableCredit($note));
        foreach ([['amount' => 90], ['payment_account_id' => $this->account('1110')->id], ['payment_date' => now()->subDay()->toDateString()]] as $change) {
            try {
                $this->credits()->refund($note, $change + ['amount' => 100, 'payment_date' => now()->toDateString(), 'payment_account_id' => $this->account('1120')->id, 'idempotency_key' => $key], $this->admin());
                $this->fail('conflict');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('idempotency_key', $e->errors());
            }
        }
        // HTTP replay through the controller.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.refund', $note), ['payment_account_id' => $this->account('1120')->id, 'amount' => 100, 'payment_date' => now()->toDateString(), 'idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->assertSame(1, CustomerCreditRefund::count());
    }

    public function test_refund_reversal_posts_the_exact_opposite_and_restores_credit(): void
    {
        $note = $this->creditNote(); // 230
        $refund = $this->refund($note, 230, '1120');
        $ar = $this->glBalance('1200');
        $bank = $this->glBalance('1120');
        $reversed = $this->credits()->reverseRefund($refund, 'Transfer bounced', $this->admin());
        $this->assertNotNull($reversed->reversed_at);
        $this->assertNotNull($reversed->reversal_journal_id);
        $reversal = $reversed->reversalJournal;
        $original = $reversed->journalEntry;
        $this->assertSame('posted', $original->fresh()->status, 'the original journal is never edited');
        $this->assertSame((float) $original->total_debit, (float) $reversal->total_debit);
        foreach ($original->lines as $line) {
            $this->assertTrue($reversal->lines->contains(fn ($l) => (int) $l->chart_of_account_id === (int) $line->chart_of_account_id && (float) $l->debit === (float) $line->credit && (float) $l->credit === (float) $line->debit));
        }
        $this->assertSame($ar - 230, $this->glBalance('1200'));
        $this->assertSame($bank + 230, $this->glBalance('1120'));
        $this->assertSame(230.0, $this->credits()->availableCredit($note));
        $this->assertSame(1, CustomerCreditRefund::count(), 'row kept');
        $this->assertArReconciled('after refund reversal');
        // Repeated reversal is a no-op; the credit can be refunded again.
        $this->credits()->reverseRefund($refund->fresh(), 'again', $this->admin());
        $this->assertSame('Transfer bounced', $refund->fresh()->reversal_reason);
        $this->assertSame(2, JournalEntry::whereIn('source_module', ['Customer Credit Refund', 'Manual'])->where(fn ($q) => $q->where('source_id', $refund->id)->orWhere('reference_number', $original->journal_number))->count());
        $this->refund($note, 230);
        $this->assertSame(0.0, $this->credits()->availableCredit($note));
        $this->assertDatabaseHas('activity_logs', ['action' => 'Customer refund reversed']);
        try {
            $this->credits()->reverseRefund(CustomerCreditRefund::latest('id')->firstOrFail(), '   ', $this->admin());
            $this->fail('reason required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
    }

    public function test_mixed_partial_resolution_keeps_history_and_reconciles(): void
    {
        $note = $this->creditNote(5); // 575
        $b = $this->invoice();
        $this->allocate($note, $b, 200);
        $this->refund($note, 100);
        $this->assertSame(275.0, $this->credits()->availableCredit($note));
        $this->assertSame(['credit' => 575.0, 'applied' => 200.0, 'refunded' => 100.0, 'available' => 275.0, 'status' => 'partially_used'], $this->credits()->summary($note));
        $this->assertArReconciled('after mixed resolution');
        $summary = CustomerWorkspacePanels::summary($this->customer, $this->admin());
        $this->assertSame(275.0, $summary['available_credit']);
        $this->assertSame(round($summary['outstanding'] - 275.0, 2), $summary['net_position']);
        $buckets = AgeingBuckets::fromRows(CustomerInvoice::whereKey($b->id)->get(['id', 'due_date', 'balance_amount']));
        $this->assertSame(2100.0, $buckets['Current']);
    }

    // ------------------------------------------- workspaces, permissions, scope (39-45)

    public function test_note_invoice_and_customer_workspaces_show_the_resolution(): void
    {
        $note = $this->creditNote(); // 230
        $target = $this->invoice();
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertOk();
        $page->assertSee(__('customer_notes.sec_resolution'))->assertSee(__('customer_notes.apply_credit'))->assertSee(__('customer_notes.refund_customer'))->assertSee($target->invoice_number);
        $this->allocate($note, $target, 100);
        $this->refund($note, 30);
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertOk();
        $page->assertSee('SAR 100.00')->assertSee('SAR 30.00')->assertSee(__('customer_notes.credit_status_partially_used'))->assertSee(__('customer_notes.reverse'));
        $invoicePage = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $target))->assertOk();
        $invoicePage->assertSee(__('customer_notes.applied_credits'))->assertSee($note->note_number)->assertSee(__('customer_notes.cash_received'))->assertSee('SAR 2,200.00');
        $this->actingAs($this->admin())->get(route('admin.master.customers.show', $this->customer))->assertOk()->assertSee(__('customer_notes.available_credit'));
        $register = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.credits.index'))->assertOk();
        $register->assertSee($note->note_number)->assertSee('100.00')->assertSee('30.00')->assertSee(__('customer_notes.credit_status_partially_used'));
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.credits.index', ['status' => 'fully_used']))->assertOk()->assertDontSee($note->note_number);
    }

    public function test_permissions_and_scope_protect_allocation_refund_and_reversals(): void
    {
        $note = $this->creditNote(); // 230
        $target = $this->invoice();
        $processor = $this->user(['Accounts Receivable' => ['view', 'process']]);
        $viewer = $this->user(['Accounts Receivable' => ['view'], 'Customers' => ['view']]);
        $payload = ['customer_invoice_id' => $target->id, 'amount' => 50, 'allocation_date' => now()->toDateString()];
        $refundPayload = ['payment_account_id' => $this->account('1120')->id, 'amount' => 50, 'payment_date' => now()->toDateString()];
        $this->actingAs($viewer)->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), $payload)->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.accounting.accounts-receivable.credits.refund', $note), $refundPayload)->assertForbidden();
        $this->actingAs($processor)->post(route('admin.accounting.accounts-receivable.credits.refund', $note), $refundPayload)->assertForbidden();
        $this->actingAs($processor)->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), $payload)->assertSessionHasNoErrors();
        $allocation = CustomerCreditAllocation::latest('id')->firstOrFail();
        $this->actingAs($processor)->post(route('admin.accounting.accounts-receivable.credits.allocations.reverse', [$note, $allocation]), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.refund', $note), $refundPayload)->assertSessionHasNoErrors();
        $refund = CustomerCreditRefund::latest('id')->firstOrFail();
        $this->actingAs($processor)->post(route('admin.accounting.accounts-receivable.credits.refunds.reverse', [$note, $refund]), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(0, CustomerCreditRefund::count() - 1);
        // Project-scoped user of another project: 404 on the note, the register hides it, the foreign invoice cannot be a target.
        $other = $this->user(['Accounts Receivable' => ['view', 'process', 'approve']], $this->otherProject);
        $this->actingAs($other)->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), $payload)->assertNotFound();
        $this->actingAs($other)->post(route('admin.accounting.accounts-receivable.credits.refund', $note), $refundPayload)->assertNotFound();
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.credits.index'))->assertOk()->assertDontSee($note->note_number);
        $same = $this->user(['Accounts Receivable' => ['view', 'process', 'approve']], $this->project);
        $foreignTarget = $this->invoice($this->customer, $this->otherProject);
        $this->actingAs($same)->post(route('admin.accounting.accounts-receivable.credits.allocate', $note), ['customer_invoice_id' => $foreignTarget->id, 'amount' => 10, 'allocation_date' => now()->toDateString()])->assertNotFound();
        $this->actingAs($same)->get(route('admin.accounting.accounts-receivable.credits.index'))->assertOk()->assertSee($note->note_number);
        // Reversals by an approver, with a reason, work once.
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.allocations.reverse', [$note, $allocation]), ['reason' => 'wrong invoice'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.refunds.reverse', [$note, $refund]), ['reason' => 'bounced'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.credits.refunds.reverse', [$note, $refund]), [])->assertSessionHasErrors('reason');
        $this->assertSame(230.0, $this->credits()->availableCredit($note));
    }

    // ------------------------------------------------------- ZATCA non-effect (37, 49-50)

    public function test_resolution_leaves_the_credit_note_zatca_record_untouched(): void
    {
        $note = $this->creditNote();
        $this->fakeZatca();
        $record = app(ZatcaSubmissionService::class)->process($note, $this->admin());
        $this->assertSame('cleared', $record->status);
        $identity = [$record->uuid, $record->invoice_hash, $record->icv, $record->signed_at->toDateTimeString(), $this->signedXml($record)];
        $this->allocate($note, $this->invoice(), 100);
        $refund = $this->refund($note, 50);
        $this->credits()->reverseRefund($refund, 'x', $this->admin());
        $fresh = $record->fresh();
        $this->assertSame($identity, [$fresh->uuid, $fresh->invoice_hash, $fresh->icv, $fresh->signed_at->toDateTimeString(), $this->signedXml($fresh)]);
        $this->assertSame('cleared', $fresh->status);
        $this->assertSame(1, $fresh->submission_attempts);
        $this->assertSame(1, ZatcaInvoiceRecord::where('customer_adjustment_note_id', $note->id)->count());
        $this->assertSame('cleared', $note->fresh()->zatca_status);
    }
}
