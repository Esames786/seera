<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\Accounting\PostingService;
use App\Services\UserAccessScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    public function index(Request $request): View
    {
        $entries = $this->accessibleEntries()->with(['costCenter', 'creator'])
            ->withCount('lines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($q) => $q->where('journal_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->when($request->filled('source'), fn ($q) => $q->where('source_module', $request->string('source')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('journal_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('journal_date', '<=', $request->date('to')))
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.accounting.journal-entries.index', [
            'entries' => $entries,
            'draftCount' => $this->accessibleEntries()->where('status', 'draft')->count(),
            'postedCount' => $this->accessibleEntries()->where('status', 'posted')->count(),
            'cancelledCount' => $this->accessibleEntries()->where('status', 'cancelled')->count(),
            'postedTotal' => (float) $this->accessibleEntries()->where('status', 'posted')->sum('total_debit'),
        ] + $this->formOptions());
    }

    public function create(): View
    {
        return view('admin.accounting.journal-entries.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines, $totals] = $this->validated($request);

        $entry = DB::transaction(function () use ($data, $lines, $totals, $request) {
            $this->assertVatLinesInOpenPeriod($data['journal_date'], array_column($lines, 'chart_of_account_id'));
            $entry = JournalEntry::create($data + $totals + [
                'journal_number' => JournalEntry::nextNumber((int) date('Y', strtotime($data['journal_date']))),
                'created_by' => $request->user()->id,
            ]);

            $entry->lines()->createMany($lines);

            return $entry;
        });

        ActivityLog::record($request, 'Accounting', 'Created journal entry', $entry->journal_number);

        return redirect()->route('admin.accounting.journal-entries.show', $entry)
            ->with('status', 'Journal entry "'.$entry->journal_number.'" created successfully.');
    }

    public function show(JournalEntry $journal_entry): View
    {
        $this->assertWholeEntryAccess($journal_entry);
        $journal_entry->load(['lines.account', 'lines.costCenter', 'lines.project', 'lines.site', 'costCenter', 'creator', 'poster']);

        return view('admin.accounting.journal-entries.show', ['entry' => $journal_entry]);
    }

    public function edit(JournalEntry $journal_entry): View
    {
        $this->assertWholeEntryAccess($journal_entry);
        if (! $journal_entry->isEditable()) {
            abort(403, 'A posted or cancelled journal entry cannot be edited.');
        }

        $journal_entry->load('lines');

        return view('admin.accounting.journal-entries.edit', ['entry' => $journal_entry] + $this->formOptions());
    }

    public function update(Request $request, JournalEntry $journal_entry): RedirectResponse
    {
        $this->assertWholeEntryAccess($journal_entry);
        if (! $journal_entry->isEditable()) {
            return back()->withErrors(['journal' => 'A posted or cancelled journal entry cannot be edited.']);
        }

        [$data, $lines, $totals] = $this->validated($request);

        DB::transaction(function () use ($journal_entry, $data, $lines, $totals) {
            // Re-check under lock: a posting that landed since the form was opened wins (F11).
            $entry = JournalEntry::whereKey($journal_entry->id)->lockForUpdate()->firstOrFail();
            $this->assertWholeEntryAccess($entry);
            if (! $entry->isEditable()) {
                throw ValidationException::withMessages(['journal' => 'This journal entry was posted or cancelled while you were editing it; your changes were not saved.']);
            }

            $this->assertVatLinesInOpenPeriod($data['journal_date'], array_column($lines, 'chart_of_account_id'));
            $entry->update($data + $totals);
            $entry->lines()->delete();
            $entry->lines()->createMany($lines);
        });

        ActivityLog::record($request, 'Accounting', 'Updated journal entry', $journal_entry->journal_number);

        return redirect()->route('admin.accounting.journal-entries.show', $journal_entry)
            ->with('status', 'Journal entry "'.$journal_entry->journal_number.'" updated successfully.');
    }

    public function destroy(Request $request, JournalEntry $journal_entry): RedirectResponse
    {
        $this->assertWholeEntryAccess($journal_entry);
        if ($journal_entry->status === 'posted') {
            return back()->withErrors(['journal' => 'A posted journal entry cannot be deleted. Cancel it instead.']);
        }

        $number = $journal_entry->journal_number;

        DB::transaction(function () use ($journal_entry) {
            $entry = JournalEntry::whereKey($journal_entry->id)->lockForUpdate()->firstOrFail();
            $this->assertWholeEntryAccess($entry);
            if ($entry->status === 'posted') {
                throw ValidationException::withMessages(['journal' => 'This journal entry was posted in the meantime and cannot be deleted.']);
            }
            $entry->delete();
        });

        ActivityLog::record($request, 'Accounting', 'Deleted journal entry', $number);

        return redirect()->route('admin.accounting.journal-entries.index')
            ->with('status', 'Journal entry "'.$number.'" deleted successfully.');
    }

    /**
     * Only a balanced, unposted journal can reach the general ledger.
     */
    public function post(Request $request, JournalEntry $journal_entry): RedirectResponse
    {
        DB::transaction(function () use ($journal_entry, $request) {
            $entry = JournalEntry::whereKey($journal_entry->id)->lockForUpdate()->firstOrFail();
            $this->assertWholeEntryAccess($entry);
            if ($entry->status === 'posted') {
                throw ValidationException::withMessages(['journal' => 'This journal entry is already posted.']);
            }
            if ($entry->status === 'cancelled') {
                throw ValidationException::withMessages(['journal' => 'A cancelled journal entry cannot be posted.']);
            }
            // Check ALL persisted lines only after authorizing the entire entry.
            $lines = $entry->lines()->withoutGlobalScopes()->lockForUpdate()->get();
            $debit = (int) round($lines->sum('debit') * 100);
            $credit = (int) round($lines->sum('credit') * 100);
            if ($lines->count() < 2 || $debit <= 0 || $debit !== $credit
                || $debit !== (int) round((float) $entry->total_debit * 100)
                || $credit !== (int) round((float) $entry->total_credit * 100)
                || $lines->contains(fn ($line) => ! $line->chart_of_account_id
                    || (float) $line->debit < 0 || (float) $line->credit < 0
                    || ((float) $line->debit > 0 && (float) $line->credit > 0))) {
                throw ValidationException::withMessages(['journal' => 'Saved journal lines must balance and match the header totals before posting. Review and save the complete journal.']);
            }

            $accountIds = $lines->pluck('chart_of_account_id')->all();
            $this->assertLineAccountsActive($accountIds, 'journal');
            $this->assertVatLinesInOpenPeriod($entry->journal_date, $accountIds);

            $entry->update([
                'status' => 'posted',
                'posted_by' => $request->user()->id,
                'posted_at' => now(),
            ]);
        });

        ActivityLog::record($request, 'Accounting', 'Posted journal entry', $journal_entry->journal_number);

        return redirect()->route('admin.accounting.journal-entries.show', $journal_entry)
            ->with('status', 'Journal entry "'.$journal_entry->journal_number.'" posted to the general ledger.');
    }

    public function cancel(Request $request, JournalEntry $journal_entry): RedirectResponse
    {
        $this->assertWholeEntryAccess($journal_entry);
        if ($journal_entry->status === 'posted') {
            return back()->withErrors(['journal' => 'A posted journal entry cannot be cancelled in this phase.']);
        }

        DB::transaction(function () use ($journal_entry) {
            $entry = JournalEntry::whereKey($journal_entry->id)->lockForUpdate()->firstOrFail();
            $this->assertWholeEntryAccess($entry);
            if ($entry->status === 'posted') {
                throw ValidationException::withMessages(['journal' => 'This journal entry was posted in the meantime and cannot be cancelled.']);
            }
            $entry->update(['status' => 'cancelled']);
        });

        ActivityLog::record($request, 'Accounting', 'Cancelled journal entry', $journal_entry->journal_number);

        return back()->with('status', 'Journal entry "'.$journal_entry->journal_number.'" cancelled. It stays in the audit history.');
    }

    /** Journal screens/actions require every line; line-scoped ledger reports remain unchanged. */
    private function accessibleEntries(): Builder
    {
        return JournalEntry::query()->whereDoesntHave('lines', function ($lines) {
            $lines->withoutGlobalScopes()->whereNotIn('journal_entry_lines.id',
                JournalEntryLine::query()->select('journal_entry_lines.id'));
        });
    }

    private function assertWholeEntryAccess(JournalEntry $entry): void
    {
        abort_unless($this->accessibleEntries()->whereKey($entry->id)->exists(), 403,
            'You must have access to every line of this journal. Ask a company-level accountant to review it.');
    }

    /** @return array{0: array, 1: array, 2: array} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'source_module' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'status' => ['required', 'in:draft,approved,cancelled'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.cost_center_id' => ['nullable', 'exists:cost_centers,id'],
            'lines.*.project_id' => ['nullable', 'exists:projects,id'],
            'lines.*.site_id' => ['nullable', 'exists:sites,id'],
        ], [
            'lines.required' => 'A journal entry needs at least two lines.',
            'lines.min' => 'A journal entry needs at least two lines.',
        ]);

        $lines = collect($data['lines'])
            ->filter(fn ($line) => filled($line['chart_of_account_id'] ?? null)
                && ((float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0))
            ->map(fn ($line) => [
                'chart_of_account_id' => $line['chart_of_account_id'],
                'description' => $line['description'] ?? null,
                'debit' => round((float) ($line['debit'] ?? 0), 2),
                'credit' => round((float) ($line['credit'] ?? 0), 2),
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'project_id' => $line['project_id'] ?? null,
                'site_id' => $line['site_id'] ?? null,
            ])
            ->values()
            ->all();

        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        // The balance rule is what makes a journal postable, so it is enforced here.
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry needs at least two lines with an account and an amount.',
            ]);
        }

        if (abs($totalDebit - $totalCredit) >= 0.01) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit ('.number_format($totalDebit, 2).') must equal total credit ('.number_format($totalCredit, 2).').',
            ]);
        }

        // Every line must sit on an account that can still take postings (F01).
        $this->assertLineAccountsActive(array_column($lines, 'chart_of_account_id'), 'lines');

        // A manual line on a VAT control account inside a sealed period would change that return (F03).
        $this->assertVatLinesInOpenPeriod($data['journal_date'], array_column($lines, 'chart_of_account_id'));

        // A scoped user may only write lines inside their own projects and sites (F07); the
        // shared write middleware checks top-level fields, not nested journal lines.
        $lines = $this->linesWithinScope($request->user(), $lines);

        unset($data['lines']);

        return [$data, $lines, ['total_debit' => $totalDebit, 'total_credit' => $totalCredit]];
    }

    /** @return array<int, array<string, mixed>> */
    private function linesWithinScope(User $user, array $lines): array
    {
        $scope = $user->effectiveAccessScope();

        if ($scope === 'company') {
            return $lines;
        }

        $scopes = app(UserAccessScopeService::class);
        $projectIds = $scopes->projectIdsFor($user);
        abort_if($projectIds === [] || in_array($scope, ['warehouse'], true), 403, 'Your account scope cannot post journal entries.');

        foreach ($lines as &$line) {
            $line['project_id'] = $line['project_id'] ?: $scopes->defaultProjectIdFor($user);
            abort_unless(in_array((int) $line['project_id'], $projectIds, true), 403, 'A journal line points to a project outside your access scope.');

            if ($scope === 'site') {
                $line['site_id'] = $line['site_id'] ?: $user->site_id;
                abort_unless((int) $line['site_id'] === (int) $user->site_id, 403, 'A journal line points to a site outside your access scope.');
            } elseif ($line['site_id']) {
                $site = Site::withoutGlobalScopes()->find($line['site_id']);
                abort_unless($site && in_array((int) $site->project_id, $projectIds, true), 403, 'A journal line points to a site outside your access scope.');
            }
        }

        return $lines;
    }

    /**
     * Refuse a journal that touches a missing or inactive account (F01).
     */
    private function assertLineAccountsActive(array $accountIds, string $errorKey): void
    {
        $accountIds = array_values(array_unique(array_filter(array_map('intval', $accountIds))));
        $inactive = ChartOfAccount::withoutGlobalScopes()
            ->whereIn('id', $accountIds)
            ->where('status', '!=', 'active')
            ->get(['account_code', 'account_name']);
        $missing = count($accountIds) - ChartOfAccount::withoutGlobalScopes()->whereIn('id', $accountIds)->count();

        if ($inactive->isNotEmpty() || $missing > 0) {
            $names = $inactive->map(fn ($a) => $a->account_code.' '.$a->account_name)->implode(', ');
            throw ValidationException::withMessages([
                $errorKey => 'Nothing was recorded: '.($names !== '' ? 'account '.$names.' is inactive' : 'a line account no longer exists').'. Choose an active account.',
            ]);
        }
    }

    private function assertVatLinesInOpenPeriod($date, array $accountIds): void
    {
        $posting = app(PostingService::class);

        if (array_intersect(array_map('intval', $accountIds), $posting->vatAccountIds()) !== []) {
            $posting->assertVatPeriodOpen($date, 'This journal touches a VAT account and');
        }
    }

    private function formOptions(): array
    {
        return [
            'accounts' => ChartOfAccount::where('status', 'active')->orderBy('account_code')->get(),
            'costCenters' => CostCenter::where('status', 'active')->orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'sites' => Site::orderBy('name')->get(),
            'sourceModules' => JournalEntry::SOURCE_MODULES,
            'statuses' => JournalEntry::STATUSES,
        ];
    }
}
