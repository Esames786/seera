<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance correctness sprint F06: the general ledger and the financial
 * reports agree with the posted journals for movements before, inside and
 * after the selected range, across pages, net of reversals, and the CSV
 * export carries the same figures as the screen.
 */
class FinanceReportBalancesTest extends TestCase
{
    use RefreshDatabase;

    private ChartOfAccount $cash;

    private ChartOfAccount $capital;

    private ChartOfAccount $expense;

    private ChartOfAccount $revenue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->cash = $this->account('1199', 'Test Cash Box', 'asset', 'debit', 1000);
        $this->capital = $this->account('3199', 'Test Capital', 'equity', 'credit', 1000);
        $this->expense = $this->account('5999', 'Test Expense', 'expense', 'debit');
        $this->revenue = $this->account('4999', 'Test Revenue', 'revenue', 'credit');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    private function account(string $code, string $name, string $type, string $normal, float $opening = 0): ChartOfAccount
    {
        return ChartOfAccount::create([
            'account_code' => $code, 'account_name' => $name, 'account_type' => $type,
            'normal_balance' => $normal, 'opening_balance' => $opening, 'status' => 'active',
        ]);
    }

    /** A posted two-line journal, optionally tagged with a project. */
    private function postedJournal(string $date, ChartOfAccount $debit, ChartOfAccount $credit, float $amount, ?int $projectId = null): JournalEntry
    {
        $entry = JournalEntry::create([
            'journal_number' => JournalEntry::nextNumber(2026), 'journal_date' => $date, 'source_module' => 'Manual',
            'description' => 'F06 fixture', 'total_debit' => $amount, 'total_credit' => $amount, 'status' => 'posted',
            'posted_at' => now(), 'created_by' => $this->admin()->id, 'posted_by' => $this->admin()->id,
        ]);
        $entry->lines()->createMany([
            ['chart_of_account_id' => $debit->id, 'debit' => $amount, 'credit' => 0, 'project_id' => $projectId],
            ['chart_of_account_id' => $credit->id, 'debit' => 0, 'credit' => $amount, 'project_id' => $projectId],
        ]);

        return $entry;
    }

    private function movements(): void
    {
        $this->postedJournal('2026-01-15', $this->cash, $this->capital, 200);   // before the range
        $this->postedJournal('2026-02-10', $this->cash, $this->capital, 300);   // inside
        $this->postedJournal('2026-03-20', $this->cash, $this->capital, 500);   // after
        $this->postedJournal('2026-01-20', $this->expense, $this->cash, 50);    // before
        $this->postedJournal('2026-02-12', $this->expense, $this->cash, 70);    // inside
        $this->postedJournal('2026-03-25', $this->expense, $this->cash, 90);    // after
    }

    private function range(): array
    {
        return ['from' => '2026-02-01', 'to' => '2026-02-28'];
    }

    private function row(array $rows, string $code): array
    {
        foreach ($rows as $row) {
            if ($row['account_code'] === $code) {
                return $row;
            }
        }
        $this->fail("account $code missing from the report");
    }

    public function test_profit_loss_excludes_nonzero_openings_but_as_of_reports_keep_them(): void
    {
        $this->expense->update(['opening_balance' => 123]);
        $this->revenue->update(['opening_balance' => 456]);
        $range = ['from' => '2040-01-01', 'to' => '2040-01-31'];
        $pl = $this->actingAs($this->admin())->get(route('admin.accounting.reports.profit-loss', $range))->assertOk();
        $this->assertSame(0.0, $this->row($pl->viewData('expenses')->all(), '5999')['balance']);
        $this->assertSame(0.0, $this->row($pl->viewData('revenue')->all(), '4999')['balance']);
        $csv = $this->get(route('admin.accounting.reports.profit-loss', $range + ['export' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('5999,"Test Expense",0', $csv);
        $this->assertMatchesRegularExpression('/4999,"Test Revenue",-?0\r?\n/', $csv);

        $trial = $this->get(route('admin.accounting.reports.trial-balance', $range))->assertOk();
        $this->assertSame(123.0, $this->row($trial->viewData('rows')->all(), '5999')['debit_balance']);
        $this->assertSame(456.0, $this->row($trial->viewData('rows')->all(), '4999')['credit_balance']);
    }

    public function test_balance_sheet_and_trial_balance_are_as_of_the_range_while_profit_and_loss_is_period_only(): void
    {
        $this->movements();

        // Balance sheet: opening 1,000 + 200 + 300 − 50 − 70 = 1,380; nothing after the range.
        $sheet = $this->actingAs($this->admin())->get(route('admin.accounting.reports.balance-sheet', $this->range()))->assertOk();
        $this->assertSame(1380.0, $this->row($sheet->viewData('assets')->all(), '1199')['balance']);
        $this->assertSame(1500.0, $this->row($sheet->viewData('equity')->all(), '3199')['balance'], 'opening 1,000 + 200 + 300');
        $this->assertSame(
            round($sheet->viewData('totalAssets'), 2),
            round($sheet->viewData('totalLiabilities') + $sheet->viewData('totalEquity') + $sheet->viewData('netProfit'), 2),
            'assets = liabilities + equity + result'
        );

        // Trial balance: the same as-of figures, and debits still equal credits.
        $trial = $this->actingAs($this->admin())->get(route('admin.accounting.reports.trial-balance', $this->range()))->assertOk();
        $this->assertSame(1380.0, $this->row($trial->viewData('rows')->all(), '1199')['debit_balance']);
        $this->assertSame(120.0, $this->row($trial->viewData('rows')->all(), '5999')['debit_balance'], 'expense to date: 50 + 70');
        $this->assertSame(round($trial->viewData('totalDebit'), 2), round($trial->viewData('totalCredit'), 2));

        // Profit & loss: the period only, so the January 50 and the March 90 are excluded.
        $pl = $this->actingAs($this->admin())->get(route('admin.accounting.reports.profit-loss', $this->range()))->assertOk();
        $this->assertSame(70.0, $this->row($pl->viewData('expenses')->all(), '5999')['balance']);

        // The export carries the screen's figures.
        $csv = $this->actingAs($this->admin())->get(route('admin.accounting.reports.balance-sheet', $this->range() + ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('Assets,1199,"Test Cash Box",1380', $csv->streamedContent());
    }

    public function test_general_ledger_opening_includes_movement_before_the_range_and_the_running_balance_continues_across_pages(): void
    {
        $this->movements();

        $ledger = $this->actingAs($this->admin())->get(route('admin.accounting.general-ledger', $this->range() + ['account' => $this->cash->id]))->assertOk();
        $this->assertSame(1150.0, $ledger->viewData('openingBalance'), 'opening 1,000 + 200 − 50 before February');
        $this->assertSame([1450.0, 1380.0], $ledger->viewData('lines')->getCollection()->pluck('running_balance')->map(fn ($v) => (float) $v)->all());

        // 25 more February lines push the ledger onto a second page.
        for ($i = 0; $i < 25; $i++) {
            $this->postedJournal('2026-02-15', $this->cash, $this->capital, 10);
        }

        $pageOne = $this->actingAs($this->admin())->get(route('admin.accounting.general-ledger', $this->range() + ['account' => $this->cash->id]))->assertOk();
        $this->assertSame(1560.0, (float) $pageOne->viewData('lines')->getCollection()->last()->running_balance, '1,150 + 300 − 70 + 18 × 10');

        $pageTwo = $this->actingAs($this->admin())->get(route('admin.accounting.general-ledger', $this->range() + ['account' => $this->cash->id, 'page' => 2]))->assertOk();
        $this->assertSame(1570.0, (float) $pageTwo->viewData('lines')->getCollection()->first()->running_balance, 'page two continues where page one ended');
        $this->assertSame(1150.0, $pageTwo->viewData('openingBalance'), 'the opening does not change with the page');

        // Cash flow uses the same opening rule.
        $cashFlow = $this->actingAs($this->admin())->get(route('admin.accounting.reports.cash-flow', $this->range()))->assertOk();
        $this->assertSame(
            round($cashFlow->viewData('openingCash') + $cashFlow->viewData('cashIn') - $cashFlow->viewData('cashOut'), 2),
            round($cashFlow->viewData('closingCash'), 2)
        );
    }

    public function test_project_cost_report_is_net_of_reversals(): void
    {
        $project = Project::create(['name' => 'F06 Project', 'code' => 'PRJ-F06', 'status' => 'active']);

        $this->postedJournal('2026-02-05', $this->expense, $this->cash, 100, $project->id);
        $this->postedJournal('2026-02-06', $this->cash, $this->expense, 100, $project->id);   // reversal
        $this->postedJournal('2026-02-07', $this->cash, $this->revenue, 400, $project->id);
        $this->postedJournal('2026-02-08', $this->revenue, $this->cash, 150, $project->id);   // partial reversal

        $report = $this->actingAs($this->admin())->get(route('admin.accounting.reports.project-cost-report', $this->range()))->assertOk();
        $row = $report->viewData('rows')->first(fn ($row) => $row['project']->id === $project->id);

        $this->assertSame(0.0, $row['cost'], 'a reversed cost is not a cost');
        $this->assertSame(250.0, $row['revenue'], '400 − 150');
        $this->assertSame(250.0, $row['margin']);

        $csv = $this->actingAs($this->admin())->get(route('admin.accounting.reports.project-cost-report', $this->range() + ['export' => 'csv']));
        $this->assertStringContainsString('"F06 Project",,0,0,0,250,0,0,250', $csv->streamedContent());
    }
}
