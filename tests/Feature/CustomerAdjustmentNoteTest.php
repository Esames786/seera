<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\CustomerAdjustmentNote;
use App\Models\CustomerInvoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\VatPeriod;
use App\Models\VatTransaction;
use App\Models\ZatcaEgsUnit;
use App\Models\ZatcaInvoiceRecord;
use App\Services\Accounting\CustomerNoteService;
use App\Services\Accounting\PostingService;
use App\Services\Accounting\ProjectCostReport;
use App\Services\Zatca\ZatcaSubmissionService;
use App\Support\AgeingBuckets;
use App\Support\Workspace\CustomerWorkspacePanels;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\OnboardsZatcaUnit;
use Tests\TestCase;

/**
 * Customer credit / debit notes: AR correction documents with their own
 * journal, VAT correction, balance application, immutable snapshots and
 * ZATCA 381 / 383 documents on the same EGS chain. ZATCA itself is faked.
 */
class CustomerAdjustmentNoteTest extends TestCase
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
        $this->project = Project::create(['name' => 'Note Tower', 'code' => 'PRJ-NOTE', 'status' => 'active']);
        $this->otherProject = Project::create(['name' => 'Other Site', 'code' => 'PRJ-OTHER', 'status' => 'active']);
        $this->customer = Customer::create(['name' => 'Riyadh Developers LLC', 'code' => 'CUS-NOTE', 'type' => 'Company', 'vat_number' => '300987654300003', 'cr_number' => '1010654321',
            'street' => 'Olaya Street', 'building_number' => '1234', 'district' => 'Al Malaz', 'city' => 'Riyadh', 'postal_code' => '11564', 'country_code' => 'SA', 'status' => 'active']);
        $this->otherCustomer = Customer::create(['name' => 'Jeddah Builders', 'code' => 'CUS-OTHER', 'type' => 'Company', 'vat_number' => '300111222300003', 'street' => 'Corniche', 'building_number' => '4321',
            'district' => 'Al Hamra', 'city' => 'Jeddah', 'postal_code' => '21411', 'country_code' => 'SA', 'status' => 'active']);
        $this->unit = $this->onboardedUnit();
    }

    // ---------------------------------------------------------------- helpers

    /** An approved (posted) standard invoice: 10 × 100 + 5 × 200 = 2,000 + 300 VAT = 2,300. */
    private function invoice(array $extra = [], ?array $lines = null, bool $approve = true): CustomerInvoice
    {
        $lines ??= [['description' => 'Concrete works', 'quantity' => 10, 'unit_price' => 100], ['description' => 'Steel works', 'quantity' => 5, 'unit_price' => 200]];
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.store'), $extra + [
            'customer_id' => $this->customer->id, 'invoice_date' => now()->toDateString(), 'supply_date' => now()->toDateString(), 'project_id' => $this->project->id,
            'due_date' => now()->addDays(30)->toDateString(), 'vat_rate' => 15, 'invoice_type' => 'standard', 'lines' => $lines,
        ])->assertSessionHasNoErrors();
        $invoice = CustomerInvoice::latest('id')->firstOrFail();
        if ($approve) {
            $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.approve', $invoice))->assertSessionHasNoErrors();
        }
        $this->flushSession();

        return $invoice->fresh(['lines', 'customer', 'zatcaRecord']);
    }

    private function notes(): CustomerNoteService
    {
        return app(CustomerNoteService::class);
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function draft(CustomerInvoice $invoice, string $type, array $lines, array $input = []): CustomerAdjustmentNote
    {
        return $this->notes()->createDraft($invoice, $type, $input + ['note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'Returned goods'], $lines, $this->admin());
    }

    private function creditLines(CustomerInvoice $invoice, float $qty = 2, ?float $price = null, int $index = 0): array
    {
        $line = $invoice->lines[$index];

        return [['invoice_line_id' => $line->id, 'quantity' => $qty, 'unit_price' => $price ?? (float) $line->unit_price]];
    }

    private function receipt(CustomerInvoice $invoice, float $amount): void
    {
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.receipt.store', $invoice), [
            'receipt_date' => now()->toDateString(), 'receipt_account_id' => ChartOfAccount::where('account_code', '1120')->value('id'), 'payment_method' => 'Bank Transfer',
            'amount' => $amount, 'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->flushSession();
    }

    private function balanceOf(string $code): float
    {
        $account = ChartOfAccount::where('account_code', $code)->firstOrFail();
        $lines = JournalEntryLine::withoutGlobalScopes()->where('chart_of_account_id', $account->id)->whereHas('journalEntry', fn ($q) => $q->where('status', 'posted'))->get();

        return round((float) $lines->sum('debit') - (float) $lines->sum('credit'), 2);
    }

    // ---------------------------------------------------------- drafts (1-10)

    public function test_credit_note_draft_derives_relation_and_amounts_from_the_original_invoice(): void
    {
        $invoice = $this->invoice();
        $note = $this->draft($invoice, 'credit', $this->creditLines($invoice, 2));
        $this->assertSame('draft', $note->status);
        $this->assertSame('credit', $note->note_type);
        $this->assertMatchesRegularExpression('/^CN-\d{4}-\d{4}$/', $note->note_number);
        $this->assertSame($invoice->customer_id, $note->customer_id);
        $this->assertSame($invoice->project_id, $note->project_id);
        $this->assertSame('standard', $note->invoice_type);
        $this->assertSame($invoice->supply_date->toDateString(), $note->supply_date->toDateString());
        $this->assertSame('200.00', $note->taxable_amount);
        $this->assertSame('30.00', $note->vat_amount);
        $this->assertSame('230.00', $note->total_amount);
        $this->assertCount(1, $note->lines);
        $this->assertSame($invoice->lines[0]->id, $note->lines[0]->customer_invoice_line_id);
        $this->assertSame('Concrete works', $note->lines[0]->description);
    }

    public function test_debit_note_draft_may_add_a_charge_that_is_not_on_the_original_invoice(): void
    {
        $invoice = $this->invoice();
        $note = $this->draft($invoice, 'debit', [['description' => 'Crane standby', 'quantity' => 3, 'unit_price' => 150]], ['reason_code' => 'agreed_price_change', 'reason' => 'Agreed standby charge']);
        $this->assertMatchesRegularExpression('/^DN-\d{4}-\d{4}$/', $note->note_number);
        $this->assertNull($note->lines[0]->customer_invoice_line_id);
        $this->assertSame('450.00', $note->taxable_amount);
        $this->assertSame('67.50', $note->vat_amount);
        $this->assertSame('517.50', $note->total_amount);
        // A credit note cannot invent a line.
        try {
            $this->draft($invoice, 'credit', [['description' => 'Invented', 'quantity' => 1, 'unit_price' => 10]]);
            $this->fail('credit lines must reference the original');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines.0.invoice_line_id', $e->errors());
        }
    }

    public function test_notes_require_a_posted_original_invoice(): void
    {
        $draftInvoice = $this->invoice(approve: false);
        try {
            $this->draft($draftInvoice, 'credit', $this->creditLines($draftInvoice));
            $this->fail('draft invoice');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_invoice_id', $e->errors());
        }
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.create', ['invoice' => $draftInvoice->id, 'type' => 'credit']))->assertSessionHasErrors('invoice');
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.create', ['invoice' => 999999, 'type' => 'credit']))->assertNotFound();
    }

    public function test_cross_customer_and_cross_project_claims_are_refused_and_relations_come_from_the_invoice(): void
    {
        $invoice = $this->invoice();
        $base = ['customer_invoice_id' => $invoice->id, 'note_type' => 'credit', 'note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'x', 'lines' => $this->creditLines($invoice)];
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.store'), $base + ['customer_id' => $this->otherCustomer->id])->assertSessionHasErrors('customer_id');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.store'), $base + ['project_id' => $this->otherProject->id])->assertSessionHasErrors('project_id');
        $this->assertSame(0, CustomerAdjustmentNote::count());
        // A line id from another invoice is refused.
        $other = $this->invoice();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.store'), array_merge($base, ['lines' => [['invoice_line_id' => $other->lines[0]->id, 'quantity' => 1, 'unit_price' => 100]]]))
            ->assertSessionHasErrors('lines.0.invoice_line_id');
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.store'), $base)->assertSessionHasNoErrors();
        $note = CustomerAdjustmentNote::latest('id')->firstOrFail();
        $this->assertSame($this->customer->id, $note->customer_id);
        $this->assertSame($this->project->id, $note->project_id);
    }

    public function test_server_calculates_credit_and_debit_amounts_and_ignores_submitted_totals(): void
    {
        $invoice = $this->invoice();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.store'), [
            'customer_invoice_id' => $invoice->id, 'note_type' => 'credit', 'note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'Returned 3 units',
            'taxable_amount' => 1, 'vat_amount' => 1, 'total_amount' => 1,
            'lines' => [['invoice_line_id' => $invoice->lines[0]->id, 'quantity' => 3, 'unit_price' => 100, 'taxable_amount' => 999, 'vat_amount' => 999, 'total_amount' => 999]],
        ])->assertSessionHasNoErrors();
        $note = CustomerAdjustmentNote::latest('id')->firstOrFail();
        $this->assertSame(['300.00', '45.00', '345.00'], [$note->taxable_amount, $note->vat_amount, $note->total_amount]);
        $this->assertSame('345.00', $note->lines()->first()->total_amount);

        $debit = $this->draft($invoice, 'debit', [['invoice_line_id' => $invoice->lines[1]->id, 'quantity' => 1, 'unit_price' => 50, 'total_amount' => 5]]);
        $this->assertSame(['50.00', '7.50', '57.50'], [$debit->taxable_amount, $debit->vat_amount, $debit->total_amount]);
    }

    public function test_draft_save_creates_no_journal_vat_or_zatca_record(): void
    {
        $invoice = $this->invoice();
        $journals = JournalEntry::count();
        $vat = VatTransaction::count();
        $records = ZatcaInvoiceRecord::count();
        $note = $this->draft($invoice, 'credit', $this->creditLines($invoice));
        $this->assertSame($journals, JournalEntry::count());
        $this->assertSame($vat, VatTransaction::count());
        $this->assertSame($records, ZatcaInvoiceRecord::count());
        $this->assertNull($note->journal_entry_id);
        $this->assertNull($note->zatcaRecord);
        $this->assertSame('2300.00', $invoice->fresh()->balance_amount, 'a draft changes nothing on the invoice');
        $this->assertSame('not_generated', $note->fresh()->zatca_status);
    }

    // ------------------------------------------------ partial and cumulative (11-13)

    public function test_partial_credit_is_allowed_and_cumulative_credit_cannot_exceed_the_original_line(): void
    {
        $invoice = $this->invoice();
        $first = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 6)), $this->admin()); // 600 of 1,000
        $this->assertSame('posted', $first->status);
        $second = $this->draft($invoice, 'credit', $this->creditLines($invoice, 4)); // 400: exactly the remainder
        $this->assertSame(['posted'], [$first->status]);
        try {
            $this->draft($invoice, 'credit', $this->creditLines($invoice, 4.01));
            $this->fail('over-credit at save');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('400.00', $e->errors()['lines'][0]);
        }
        $this->notes()->post($second, $this->admin());
        $this->assertSame(400.0, (float) $second->fresh()->taxable_amount);
        // Nothing is left on line 1; line 2 is untouched.
        $remaining = $this->notes()->remainingCredit($invoice);
        $this->assertSame(0.0, $remaining[$invoice->lines[0]->id]);
        $this->assertSame(1000.0, $remaining[$invoice->lines[1]->id]);
        // A third draft created before the second was posted is refused at posting time (the guard runs under lock).
        $third = CustomerAdjustmentNote::create(['note_type' => 'credit', 'note_number' => 'CN-TEST-0001', 'customer_invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id,
            'project_id' => $invoice->project_id, 'invoice_type' => 'standard', 'note_date' => now()->toDateString(), 'reason_code' => 'refund', 'reason' => 'late draft', 'status' => 'draft',
            'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115]);
        $third->lines()->create(['customer_invoice_line_id' => $invoice->lines[0]->id, 'description' => 'x', 'quantity' => 1, 'unit_price' => 100, 'taxable_amount' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'total_amount' => 115]);
        try {
            $this->notes()->post($third, $this->admin());
            $this->fail('over-credit at post');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }
        $this->assertSame('draft', $third->fresh()->status);
        $this->assertNull($third->fresh()->journal_entry_id);
    }

    // ----------------------------------------------------- accounting (14-22)

    public function test_credit_note_journal_balances_reduces_ar_revenue_and_output_vat_and_leaves_the_invoice_journal(): void
    {
        $invoice = $this->invoice();
        $invoiceJournal = $invoice->journalEntry()->first();
        $before = ['ar' => $this->balanceOf('1200'), 'rev' => $this->balanceOf('4100'), 'vat' => $this->balanceOf('2210')];
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin()); // 200 + 30
        $entry = $note->journalEntry;
        $this->assertSame('posted', $entry->status);
        $this->assertSame('Customer Credit Note', $entry->source_module);
        $this->assertSame($note->id, (int) $entry->source_id);
        $this->assertSame($note->note_number, $entry->reference_number);
        $this->assertSame(round((float) $entry->total_debit, 2), round((float) $entry->total_credit, 2));
        $this->assertSame(230.0, (float) $entry->total_debit);
        $this->assertSame($before['ar'] - 230, $this->balanceOf('1200'));
        $this->assertSame($before['rev'] + 200, $this->balanceOf('4100'), 'revenue (credit balance) is reduced');
        $this->assertSame($before['vat'] + 30, $this->balanceOf('2210'), 'output VAT liability is reduced');
        // The original invoice journal is untouched.
        $this->assertSame($invoiceJournal->total_debit, $invoiceJournal->fresh()->total_debit);
        $this->assertSame($invoiceJournal->updated_at->toDateTimeString(), $invoiceJournal->fresh()->updated_at->toDateTimeString());
        $this->assertSame('posted', $invoiceJournal->fresh()->status);
        // Dimensions preserved.
        $this->assertTrue($entry->lines->every(fn ($l) => (int) $l->project_id === $this->project->id));
    }

    public function test_debit_note_journal_balances_and_increases_ar_revenue_and_output_vat(): void
    {
        $invoice = $this->invoice();
        $before = ['ar' => $this->balanceOf('1200'), 'rev' => $this->balanceOf('4100'), 'vat' => $this->balanceOf('2210')];
        $note = $this->notes()->post($this->draft($invoice, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 400]], ['reason_code' => 'agreed_price_change', 'reason' => 'agreed']), $this->admin());
        $entry = $note->journalEntry;
        $this->assertSame('Customer Debit Note', $entry->source_module);
        $this->assertSame(460.0, (float) $entry->total_debit);
        $this->assertSame((float) $entry->total_debit, (float) $entry->total_credit);
        $this->assertSame($before['ar'] + 460, $this->balanceOf('1200'));
        $this->assertSame($before['rev'] - 400, $this->balanceOf('4100'));
        $this->assertSame($before['vat'] - 60, $this->balanceOf('2210'));
    }

    public function test_credit_reduces_and_debit_increases_output_vat_rows_without_touching_the_invoice_row(): void
    {
        $invoice = $this->invoice();
        $invoiceVat = VatTransaction::where('source_module', 'Customer Invoice')->where('source_id', $invoice->id)->firstOrFail();
        $credit = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin());
        $debit = $this->notes()->post($this->draft($invoice, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 100]], ['reason_code' => 'agreed_price_change', 'reason' => 'agreed']), $this->admin());
        $creditRow = VatTransaction::where('source_module', 'Customer Credit Note')->where('source_id', $credit->id)->firstOrFail();
        $debitRow = VatTransaction::where('source_module', 'Customer Debit Note')->where('source_id', $debit->id)->firstOrFail();
        $this->assertSame(['output', -30.0, -200.0], [$creditRow->vat_type, (float) $creditRow->vat_amount, (float) $creditRow->taxable_amount]);
        $this->assertSame(['output', 15.0, 100.0], [$debitRow->vat_type, (float) $debitRow->vat_amount, (float) $debitRow->taxable_amount]);
        $this->assertSame(300.0, (float) $invoiceVat->fresh()->vat_amount, 'the original VAT row is unchanged');
        // The period total nets the three rows.
        $period = VatPeriod::whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->first();
        if ($period) {
            $period->recalculate();
            $this->assertSame(round((float) VatTransaction::where('vat_period_id', $period->id)->where('vat_type', 'output')->sum('vat_amount'), 2), (float) $period->fresh()->output_vat, 'the period total nets the negative credit row');
            $this->assertSame($period->id, $creditRow->vat_period_id);
        }
        $this->assertSame(285.0, round((float) $invoiceVat->vat_amount + (float) $creditRow->vat_amount + (float) $debitRow->vat_amount, 2));
    }

    public function test_sealed_vat_period_blocks_posting_a_note_dated_inside_it(): void
    {
        $invoice = $this->invoice();
        $period = VatPeriod::create(['period_name' => 'Sealed', 'start_date' => now()->subMonths(2)->startOfMonth()->toDateString(), 'end_date' => now()->subMonths(2)->endOfMonth()->toDateString(), 'status' => 'finalized']);
        $note = $this->draft($invoice, 'credit', $this->creditLines($invoice, 1), ['note_date' => now()->subMonths(2)->startOfMonth()->addDays(3)->toDateString()]);
        try {
            $this->notes()->post($note, $this->admin());
            $this->fail('sealed period');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('vat', $e->errors());
        }
        $this->assertSame('draft', $note->fresh()->status);
        $this->assertSame(0, JournalEntry::where('source_module', 'Customer Credit Note')->count());
        $this->assertSame(0, VatTransaction::where('vat_period_id', $period->id)->count());
    }

    public function test_project_cost_report_reflects_the_correction_once(): void
    {
        $invoice = $this->invoice();
        $report = app(ProjectCostReport::class);
        $period = new \App\Support\ReportPeriod(\Carbon\CarbonImmutable::now()->startOfYear(), \Carbon\CarbonImmutable::now()->endOfYear(), 'this_year');
        $before = $report->rows(Project::whereKey($this->project->id)->get(), $period)->first();
        $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin());
        $after = $report->rows(Project::whereKey($this->project->id)->get(), $period)->first();
        $this->assertSame(round($before['revenue'] - 200, 2), round($after['revenue'], 2), 'GL revenue carries the note once');
        $this->assertSame(round($before['invoiced'] - 230, 2), round($after['invoiced'], 2), 'invoiced figure nets the note once');
    }

    // ------------------------------------------- balance, ageing, receipts (16-26)

    public function test_credit_reduces_and_debit_increases_the_invoice_balance_ageing_and_customer_summary(): void
    {
        $invoice = $this->invoice();
        $invoice->update(['due_date' => now()->subDays(40)->toDateString()]);
        $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin()); // −230
        $this->assertSame('2070.00', $invoice->fresh()->balance_amount);
        $this->assertSame('unpaid', $invoice->fresh()->payment_status);
        $this->notes()->post($this->draft($invoice, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 100]], ['reason_code' => 'agreed_price_change', 'reason' => 'agreed']), $this->admin()); // +115
        $this->assertSame('2185.00', $invoice->fresh()->balance_amount);
        // Ageing and the GL agree.
        $buckets = AgeingBuckets::fromRows(CustomerInvoice::whereKey($invoice->id)->get(['id', 'due_date', 'balance_amount']));
        $this->assertSame(2185.0, $buckets['31-60 days']);
        $this->assertSame($this->balanceOf('1200'), round((float) CustomerInvoice::whereIn('payment_status', ['unpaid', 'partially_paid'])->sum('balance_amount'), 2), 'GL AR equals the sum of open balances');
        $summary = CustomerWorkspacePanels::summary($this->customer, $this->admin());
        $this->assertSame(2185.0, $summary['outstanding']);
        $this->assertSame(230.0, $summary['credited']);
        $this->assertSame(115.0, $summary['debited']);
        $adjustments = $invoice->fresh()->postedAdjustments();
        $this->assertSame(['credited' => 230.0, 'credit_applied' => 230.0, 'credit_unapplied' => 0.0, 'debited' => 115.0], $adjustments);
    }

    public function test_credit_on_a_paid_invoice_keeps_the_receipt_and_records_unapplied_customer_credit(): void
    {
        $invoice = $this->invoice();
        $this->receipt($invoice, 2300);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin()); // 230 credit, nothing open
        $this->assertSame('0.00', $note->applied_amount);
        $this->assertSame('230.00', $note->unapplied_amount);
        $fresh = $invoice->fresh();
        $this->assertSame('0.00', $fresh->balance_amount, 'the balance never goes negative');
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame('2300.00', $fresh->received_amount);
        $this->assertSame(1, $invoice->receipts()->count(), 'receipt history preserved');
        $this->assertSame(['credited' => 230.0, 'credit_applied' => 0.0, 'credit_unapplied' => 230.0, 'debited' => 0.0], $fresh->postedAdjustments());
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()->assertSee(__('customer_notes.unapplied'));
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.index'))->assertOk()->assertSee('230.00');
    }

    public function test_partly_paid_invoice_credit_applies_only_what_is_open_and_settles_the_invoice(): void
    {
        $invoice = $this->invoice();
        $this->receipt($invoice, 2200); // 100 open
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin()); // 230
        $this->assertSame(['100.00', '130.00'], [$note->applied_amount, $note->unapplied_amount]);
        $fresh = $invoice->fresh();
        $this->assertSame('0.00', $fresh->balance_amount);
        $this->assertSame('paid', $fresh->payment_status);
    }

    public function test_receipt_after_a_debit_note_can_settle_the_increased_balance(): void
    {
        $invoice = $this->invoice();
        $this->notes()->post($this->draft($invoice, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 100]], ['reason_code' => 'agreed_price_change', 'reason' => 'agreed']), $this->admin());
        $this->assertSame('2415.00', $invoice->fresh()->balance_amount);
        $this->receipt($invoice, 2415);
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertSame('0.00', $invoice->fresh()->balance_amount);
    }

    // ------------------------------------------- idempotency, permissions, immutability (27-32)

    public function test_double_posting_is_idempotent_and_unauthorised_posting_is_blocked(): void
    {
        $invoice = $this->invoice();
        $note = $this->draft($invoice, 'credit', $this->creditLines($invoice, 1));
        $viewer = $this->user(['Accounts Receivable' => ['view']]);
        $this->actingAs($viewer)->post(route('admin.accounting.accounts-receivable.notes.approve', $note))->assertForbidden();
        $this->assertSame('draft', $note->fresh()->status);
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.approve', $note))->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->post(route('admin.accounting.accounts-receivable.notes.approve', $note))->assertSessionHasNoErrors();
        $this->notes()->post($note->fresh(), $this->admin());
        $this->assertSame(1, JournalEntry::where('source_module', 'Customer Credit Note')->where('source_id', $note->id)->count());
        $this->assertSame(1, VatTransaction::where('source_module', 'Customer Credit Note')->where('source_id', $note->id)->count());
        $this->assertSame(1, ZatcaInvoiceRecord::where('customer_adjustment_note_id', $note->id)->count());
        $this->assertSame('2185.00', $invoice->fresh()->balance_amount, 'applied once');
        $this->assertDatabaseHas('activity_logs', ['module' => 'Accounting', 'action' => 'Posted customer credit note']);
    }

    public function test_project_scoped_user_cannot_see_or_correct_another_projects_invoice(): void
    {
        $invoice = $this->invoice();
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $other = $this->user(['Accounts Receivable' => ['view', 'create', 'edit', 'approve'], 'ZATCA Invoicing' => ['view', 'export']], $this->otherProject);
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.notes.create', ['invoice' => $invoice->id, 'type' => 'credit']))->assertNotFound();
        $this->actingAs($other)->post(route('admin.accounting.accounts-receivable.notes.store'), ['customer_invoice_id' => $invoice->id, 'note_type' => 'credit', 'note_date' => now()->toDateString(),
            'reason_code' => 'refund', 'reason' => 'x', 'lines' => $this->creditLines($invoice)])->assertNotFound();
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertNotFound();
        $this->actingAs($other)->get(route('admin.accounting.accounts-receivable.notes.index'))->assertOk()->assertDontSee($note->note_number);
        $this->actingAs($other)->get(route('admin.accounting.zatca.xml', [$note->zatcaRecord, 'signed']))->assertNotFound();
        $same = $this->user(['Accounts Receivable' => ['view']], $this->project);
        $this->actingAs($same)->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertOk()->assertSee($note->note_number);
    }

    public function test_posted_note_cannot_be_edited_and_a_stamped_note_is_immutable(): void
    {
        $invoice = $this->invoice();
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.edit', $note))->assertForbidden();
        $this->actingAs($this->admin())->put(route('admin.accounting.accounts-receivable.notes.update', $note), ['customer_invoice_id' => $invoice->id, 'note_type' => 'credit', 'note_date' => now()->toDateString(),
            'reason_code' => 'refund', 'reason' => 'changed', 'lines' => $this->creditLines($invoice, 2)])->assertSessionHasErrors();
        $this->assertSame('Returned goods', $note->fresh()->reason);
        $this->fakeZatca();
        app(ZatcaSubmissionService::class)->sign($note->fresh(), $this->admin());
        $fresh = $note->fresh();
        $this->assertNotNull($fresh->zatca_locked_at);
        $this->assertFalse($fresh->isEditable());
        $this->assertSame('signed', $fresh->zatca_status);
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertOk()->assertSee(__('customer_notes.immutable'));
    }

    public function test_customer_and_company_changes_after_posting_do_not_alter_the_note_snapshots(): void
    {
        $invoice = $this->invoice();
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $this->assertSame('Riyadh Developers LLC', $note->buyer_snapshot['name']);
        $this->assertSame('Seera Construction Co.', $note->seller_snapshot['name']);
        $this->customer->update(['name' => 'Renamed Customer', 'vat_number' => '300000000000003']);
        CompanyProfile::query()->update(['name' => 'Renamed Seller']);
        $fresh = $note->fresh();
        $this->assertSame('Riyadh Developers LLC', $fresh->buyer_snapshot['name']);
        $this->assertSame('300987654300003', $fresh->buyer_snapshot['vat_number']);
        $this->assertSame('Seera Construction Co.', $fresh->seller_snapshot['name']);
        $this->fakeZatca();
        $record = app(ZatcaSubmissionService::class)->sign($fresh, $this->admin());
        $xml = $this->signedXml($record);
        $this->assertStringContainsString('Riyadh Developers LLC', $xml);
        $this->assertStringNotContainsString('Renamed', $xml);
    }

    // ----------------------------------------------------------- ZATCA (33-44)

    public function test_credit_and_debit_note_xml_carry_the_type_code_billing_reference_and_reason(): void
    {
        $invoice = $this->invoice();
        $credit = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1), ['reason_code' => 'cancellation', 'reason' => 'Partial cancellation of the supply']), $this->admin());
        $debit = $this->notes()->post($this->draft($invoice, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 100]], ['reason_code' => 'agreed_price_change', 'reason' => 'Agreed price change']), $this->admin());
        $service = app(ZatcaSubmissionService::class);
        $creditRecord = $service->generate($credit, $this->admin());
        $debitRecord = $service->generate($debit, $this->admin());
        $this->assertSame('validated', $creditRecord->status);
        $this->assertSame('validated', $debitRecord->status);
        $this->assertSame(['381', '0100000', 'credit_note'], [$creditRecord->invoice_type_code, $creditRecord->transaction_code, $creditRecord->document_type]);
        $this->assertSame(['383', '0100000', 'debit_note'], [$debitRecord->invoice_type_code, $debitRecord->transaction_code, $debitRecord->document_type]);
        $creditXml = Storage::disk('local')->get($creditRecord->xml_path);
        $this->assertStringContainsString('<cbc:InvoiceTypeCode name="0100000">381</cbc:InvoiceTypeCode>', $creditXml);
        $this->assertStringContainsString('<cac:BillingReference>', $creditXml);
        $this->assertStringContainsString('<cbc:ID>'.$invoice->invoice_number.'</cbc:ID>', $creditXml);
        $this->assertStringContainsString('<cbc:InstructionNote>Partial cancellation of the supply</cbc:InstructionNote>', $creditXml);
        $this->assertStringContainsString('<cbc:ID>'.$credit->note_number.'</cbc:ID>', $creditXml);
        $this->assertStringContainsString('<cbc:TaxAmount currencyID="SAR">15.00</cbc:TaxAmount>', $creditXml);
        $this->assertStringContainsString('<cbc:ActualDeliveryDate>'.$invoice->supply_date->toDateString().'</cbc:ActualDeliveryDate>', $creditXml);
        $debitXml = Storage::disk('local')->get($debitRecord->xml_path);
        $this->assertStringContainsString('>383</cbc:InvoiceTypeCode>', $debitXml);
        $this->assertStringContainsString('<cbc:InstructionNote>Agreed price change</cbc:InstructionNote>', $debitXml);
        // Simplified original → simplified note on the reporting path.
        $simple = $this->invoice(['invoice_type' => 'simplified']);
        $simpleNote = $this->notes()->post($this->draft($simple, 'credit', $this->creditLines($simple, 1)), $this->admin());
        $this->assertSame('simplified', $simpleNote->invoice_type);
        $simpleRecord = $service->generate($simpleNote, $this->admin());
        $this->assertSame('0200000', $simpleRecord->transaction_code);
    }

    public function test_notes_clear_or_report_on_the_shared_chain_with_stable_identity_and_no_ledger_effect(): void
    {
        $invoice = $this->invoice();
        $this->fakeZatca();
        $service = app(ZatcaSubmissionService::class);
        $service->process($invoice, $this->admin()); // ICV 1
        $credit = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $journals = JournalEntry::count();
        $vat = VatTransaction::count();
        $balance = $invoice->fresh()->balance_amount;
        $record = $service->process($credit, $this->admin()); // ICV 2, standard → clearance
        $this->assertSame('cleared', $record->status);
        $this->assertSame(2, $record->icv);
        $this->assertSame($invoice->fresh()->zatcaRecord->invoice_hash, $record->previous_hash, 'the note chains to the invoice before it');
        $this->assertNotNull($record->cleared_xml_path);
        $this->assertSame('cleared', $credit->fresh()->zatca_status);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SANDBOX.'/invoices/clearance/single' && $r->data()['uuid'] === $record->uuid && str_contains(base64_decode($r->data()['invoice']), '>381<'));
        // Identity is stable and a double submit changes nothing.
        $uuid = $record->uuid;
        $hash = $record->invoice_hash;
        try {
            $service->submit($credit->fresh(), $this->admin());
            $this->fail('already accepted');
        } catch (\App\Services\Zatca\ZatcaException $e) {
            $this->assertSame('already_accepted', $e->errorCode);
        }
        $this->assertSame([$uuid, $hash, 1], [$record->fresh()->uuid, $record->fresh()->invoice_hash, $record->fresh()->submission_attempts]);
        $this->assertSame($journals, JournalEntry::count(), 'ZATCA never posts');
        $this->assertSame($vat, VatTransaction::count());
        $this->assertSame($balance, $invoice->fresh()->balance_amount);
        // The invoice's own record is still its own.
        $this->assertSame(1, $invoice->fresh()->zatcaRecord->icv);
        $this->assertSame($record->id, $credit->fresh()->zatcaRecord->id);
        $this->assertSame($invoice->id, $record->customer_invoice_id, 'the note record carries the original invoice for scoping');
        // Simplified note → reporting.
        $simple = $this->invoice(['invoice_type' => 'simplified']);
        $simpleNote = $this->notes()->post($this->draft($simple, 'debit', [['description' => 'Extra', 'quantity' => 1, 'unit_price' => 10]], ['reason_code' => 'agreed_price_change', 'reason' => 'x']), $this->admin());
        $simpleRecord = $service->process($simpleNote, $this->admin());
        $this->assertSame('reported', $simpleRecord->status);
        $this->assertSame(3, $simpleRecord->icv);
        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::SANDBOX.'/invoices/reporting/single' && $r->data()['uuid'] === $simpleRecord->uuid);
    }

    public function test_zatca_register_and_record_page_show_the_note(): void
    {
        $invoice = $this->invoice();
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $record = $note->zatcaRecord;
        $this->actingAs($this->admin())->get(route('admin.accounting.zatca.index'))->assertOk()->assertSee($note->note_number);
        $this->actingAs($this->admin())->get(route('admin.accounting.zatca.show', $record))->assertOk()->assertSee($note->note_number)->assertSee('Open Note')->assertSee('Returned goods');
        $this->actingAs($this->admin())->post(route('admin.accounting.zatca.generate', $record))->assertSessionHasNoErrors();
        $this->assertSame('validated', $record->fresh()->status);
        $this->assertSame('Credit note · Standard tax invoice (B2B, clearance)', $record->fresh()->documentTypeLabel());
    }

    // ------------------------------------------------------- workspaces (45-46)

    public function test_invoice_workspace_lists_notes_with_totals_and_create_actions(): void
    {
        $invoice = $this->invoice();
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk();
        $page->assertSee(__('customer_notes.adjustments'))->assertSee(__('customer_notes.new_credit'))->assertSee(__('customer_notes.new_debit'))->assertSee(__('customer_notes.no_notes'));
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 2)), $this->admin());
        $page = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk();
        $page->assertSee($note->note_number)->assertSee('SAR 230.00')->assertSee('SAR 2,070.00')->assertSee(__('customer_notes.adjusted_balance'));
        // The create form prefills the original lines and the remaining amounts.
        $form = $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.create', ['invoice' => $invoice->id, 'type' => 'credit']))->assertOk();
        $form->assertSee('Concrete works')->assertSee('800.00')->assertSee(__('customer_notes.reason_refund'));
        $viewer = $this->user(['Accounts Receivable' => ['view']]);
        $this->actingAs($viewer)->get(route('admin.accounting.accounts-receivable.show', $invoice))->assertOk()->assertDontSee(__('customer_notes.new_credit'));
        // The note workspace renders its sections and the print view.
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.show', $note))->assertOk()->assertSee(__('customer_notes.sec_summary'))->assertSee($invoice->invoice_number)->assertSee('Concrete works');
        $this->actingAs($this->admin())->get(route('admin.accounting.accounts-receivable.notes.print', $note))->assertOk()->assertSee('إشعار دائن')->assertSee($note->note_number)->assertSee('Riyadh Developers LLC');
    }

    public function test_customer_workspace_shows_the_notes_panel_and_accounting_journals(): void
    {
        $invoice = $this->invoice();
        $note = $this->notes()->post($this->draft($invoice, 'credit', $this->creditLines($invoice, 1)), $this->admin());
        $this->actingAs($this->admin())->get(route('admin.master.customers.show', $this->customer))->assertOk()->assertSee('Credit / Debit Notes');
        $rows = CustomerWorkspacePanels::query($this->customer, 'adjustments', $this->admin())->get();
        $this->assertTrue($rows->contains('id', $note->id));
        $journals = CustomerWorkspacePanels::query($this->customer, 'accounting', $this->admin())->get();
        $this->assertTrue($journals->contains('source_module', 'Customer Credit Note'));
        $this->assertSame(route('admin.accounting.accounts-receivable.notes.index', ['customer' => $this->customer->id]), CustomerWorkspacePanels::viewAllUrl($this->customer, 'adjustments'));
    }
}
