@extends('layouts.admin')

@section('title', 'Payroll Details')
@section('breadcrumb', 'HR & Payroll / Payroll / Payroll Details')

@section('content')
    @php
        /** @var \App\Models\PayrollRun $run */
        $money = fn ($v) => 'SAR '.number_format((float) $v, 2);
        $journal = $run->journalEntry;
        $selfUrl = route('admin.hr.payroll.show', $run, false);
        $accountingClass = match ($run->accounting_status) {
            \App\Models\PayrollRun::ACCOUNTING_POSTED => 'green',
            \App\Models\PayrollRun::ACCOUNTING_FAILED => 'red',
            \App\Models\PayrollRun::ACCOUNTING_REVIEW => 'yellow',
            \App\Models\PayrollRun::ACCOUNTING_REVERSED => 'gray',
            default => 'gray',
        };
        $paymentClass = $run->isPaid() ? 'green' : ($run->accounting_status === \App\Models\PayrollRun::ACCOUNTING_POSTED ? 'yellow' : 'gray');
        $sections = ['summary' => 'Run Summary', 'items' => 'Employees', 'accounting' => 'Accounting', 'payment' => 'Salary Payment'];
        if ($canExport || $canExportRole) $sections['export'] = 'Bank Export';
        if ($activity !== null) $sections['activity'] = 'Activity';
        $paymentJournal = $settlement?->journalEntry;
    @endphp

    <x-admin.page-header :title="'Payroll Run: '.$run->code" :description="$run->periodLabel().' — '.$run->period_start->toDateString().' to '.$run->period_end->toDateString().'. Read-only view; Process, Approve, Post, Pay and Reverse are explicit actions.'">
        @if ($canEdit)
            <a class="btn outline" href="{{ route('admin.hr.payroll.edit', $run) }}">Edit</a>
        @endif
        @if ($canProcess)
            <form method="POST" action="{{ route('admin.hr.payroll.process', $run) }}">
                @csrf
                <button type="submit" class="btn warning">Process Payroll</button>
            </form>
        @endif
        @if ($canApprove)
            <form method="POST" action="{{ route('admin.hr.payroll.approve', $run) }}">
                @csrf
                <button type="submit" class="btn primary">Approve Payroll</button>
            </form>
        @endif
        @if ($canPost)
            <form method="POST" action="{{ route('admin.hr.payroll.post', $run) }}">
                @csrf
                <button type="submit" class="btn primary">{{ $run->accounting_status === \App\Models\PayrollRun::ACCOUNTING_FAILED ? 'Retry Posting' : 'Post to Accounting' }}</button>
            </form>
        @endif
        @if ($canSettle)
            <a class="btn primary" href="#payment">Record Salary Payment</a>
        @endif
        @if ($canReverse && ! $run->isPaid())
            <form method="POST" action="{{ route('admin.hr.payroll.reverse', $run) }}" onsubmit="var reason = prompt('Reason for reversing this payroll posting (kept on the run):'); if (!reason) return false; this.reason.value = reason;">
                @csrf
                <input type="hidden" name="reason" value=""/>
                <button type="submit" class="btn danger" title="Posts an opposite journal; the original journal and the approved run stay in history">Reverse Posting</button>
            </form>
        @endif
        @if ($canViewJournal && $journal)
            <a class="btn outline" href="{{ route('admin.accounting.journal-entries.show', $journal) }}">View Journal</a>
        @endif
        <a class="btn outline" href="{{ route('admin.hr.payroll.index') }}">Back to Payroll</a>
    </x-admin.page-header>

    {{-- Persistent run identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $run->code }}</h2>
            <div>
                <x-admin.status-badge :status="$run->status"/>
                <span class="badge {{ $accountingClass }}">{{ $run->accountingLabel() }}</span>
                <span class="badge {{ $paymentClass }}">{{ $run->paymentLabel() }}</span>
            </div>
        </div>
        <dl>
            <dt>Period</dt><dd>{{ $run->periodLabel() }} · {{ $run->period_start->toDateString() }} → {{ $run->period_end->toDateString() }}</dd>
            <dt>Scope</dt><dd>{{ $run->branch?->name ?? 'All branches' }} / {{ $run->project?->name ?? 'All projects' }}</dd>
            <dt>Employees</dt><dd>{{ $totals->employees }}</dd>
            <dt>Approved</dt><dd>{{ $run->approver?->name ?? '-' }}{{ $run->approved_at ? ' · '.$run->approved_at->format('Y-m-d H:i') : '' }}</dd>
        </dl>
        <dl>
            <dt>Gross payroll</dt><dd>{{ $money($totals->gross) }}</dd>
            <dt>Allowances / Overtime</dt><dd>{{ $money($totals->allowances) }} / {{ $money($totals->overtime) }}</dd>
            <dt>Deductions</dt><dd>{{ $money($totals->deductions) }}</dd>
            <dt>Net payroll (payable)</dt><dd><strong>{{ $money($totals->net) }}</strong></dd>
            <dt>Accounting</dt>
            <dd>
                {{ $run->accountingLabel() }}
                @if ($journal && $canViewJournal) · <a href="{{ route('admin.accounting.journal-entries.show', $journal) }}">{{ $journal->journal_number }}</a>@endif
            </dd>
            <dt>Salary payment</dt>
            <dd>
                {{ $run->paymentLabel() }}{{ $run->settled_at ? ' · '.$run->settled_at->format('Y-m-d') : '' }}
                @if ($paymentJournal && $canViewJournal) · <a href="{{ route('admin.accounting.journal-entries.show', $paymentJournal) }}">{{ $paymentJournal->journal_number }}</a>@endif
            </dd>
        </dl>
    </div>

    <nav class="tabs workspace-nav" aria-label="Payroll run sections">
        @foreach ($sections as $key => $label)
            <a class="tab" href="#{{ $key }}">{{ $label }}</a>
        @endforeach
    </nav>

    {{-- Run Summary --}}
    <div class="card-grid" id="summary">
        <x-admin.metric-card color="blue" :value="$totals->employees" label="Employees"/>
        <x-admin.metric-card color="green" :value="$money($totals->gross)" label="Gross Payroll"/>
        <x-admin.metric-card color="red" :value="$money($totals->deductions)" label="Total Deductions"/>
        <x-admin.metric-card color="cyan" :value="$money($totals->net)" label="Net Payroll"/>
    </div>

    <x-admin.data-table title="Payroll Run Information" class="detail-table">
        <tbody>
            <tr><th>Code</th><td>{{ $run->code }}</td></tr>
            <tr><th>Month</th><td>{{ $run->periodLabel() }}</td></tr>
            <tr><th>Period</th><td>{{ $run->period_start->toDateString() }} → {{ $run->period_end->toDateString() }}</td></tr>
            <tr><th>Branch</th><td>{{ $run->branch?->name ?? 'All branches' }}</td></tr>
            <tr><th>Project</th><td>{{ $run->project?->name ?? 'All projects' }}</td></tr>
            <tr><th>Status</th><td><x-admin.status-badge :status="$run->status"/></td></tr>
            <tr><th>Processed At</th><td>{{ $run->processed_at?->format('Y-m-d H:i') ?? 'Not processed yet' }}</td></tr>
            <tr><th>Approved By</th><td>{{ $run->approver?->name ?? '-' }}</td></tr>
            <tr><th>Approved At</th><td>{{ $run->approved_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            <tr><th>Basic / Allowances / Overtime</th><td>{{ $money($totals->basic) }} / {{ $money($totals->allowances) }} / {{ $money($totals->overtime) }}</td></tr>
            <tr><th>Accounting</th><td>{{ $run->accountingLabel() }}</td></tr>
            <tr><th>Salary payment</th><td>{{ $run->paymentLabel() }}</td></tr>
            <tr><th>Notes</th><td>{{ $run->notes ?? '-' }}</td></tr>
        </tbody>
    </x-admin.data-table>

    {{-- Employees --}}
    <x-admin.data-table title="Employees" :subtitle="$totals->employees.' employees · stored values from Process; net = basic + allowances + approved overtime − deductions' " id="items">
        <thead>
            <tr><th>Employee</th><th>Department</th><th>Project / Cost Center</th><th>Present / Leave</th><th>Basic</th><th>Allowances</th><th>Overtime</th><th>Gross</th><th>Deductions</th><th>Net</th><th></th></tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>@if ($canViewEmployee && $item->employee)<a href="{{ route('admin.hr.employees.show', $item->employee) }}" style="color:var(--blue);font-weight:700">{{ $item->employee->name }}</a>@else{{ $item->employee?->name ?? '-' }}@endif <span class="small">{{ $item->employee?->employee_code }}</span></td>
                    <td>{{ $item->employee?->department?->name ?? '-' }}</td>
                    <td>{{ $item->employee?->project?->name ?? 'Head Office' }}</td>
                    <td>{{ $item->present_days }} / {{ $item->leave_days }}</td>
                    <td>{{ number_format($item->basic_salary, 2) }}</td>
                    <td>{{ number_format($item->total_allowances, 2) }}</td>
                    <td>{{ number_format($item->overtime_amount, 2) }}</td>
                    <td>{{ number_format($item->gross_amount, 2) }}</td>
                    <td>{{ number_format($item->total_deductions, 2) }}</td>
                    <td><strong>{{ number_format($item->net_amount, 2) }}</strong></td>
                    <td><a class="btn sm outline" href="{{ route('admin.hr.payroll.payslip', [$run, $item->id, 'return_to' => $selfUrl.'#items']) }}">Payslip</a></td>
                </tr>
            @empty
                <tr><td colspan="11" class="table-empty">No payroll items yet. Process this payroll run to generate them.</td></tr>
            @endforelse
        </tbody>
        <x-slot:footer>
            <span class="small">Showing {{ $items->firstItem() ?? 0 }}-{{ $items->lastItem() ?? 0 }} of {{ $items->total() }}</span>
            @if ($items->hasPages()){{ $items->links() }}@endif
        </x-slot:footer>
    </x-admin.data-table>

    {{-- Accounting --}}
    <x-admin.data-table title="Accounting" subtitle="Approval is HR authorization; posting is the financial recognition (Dr salary expense per employee with the employee's project, Cr payroll payable for the net, Cr deduction liability). Paying the salaries is the separate Salary Payment step below." class="detail-table" id="accounting">
        <tbody>
            <tr><th>Accounting state</th><td><span class="badge {{ $accountingClass }}">{{ $run->accountingLabel() }}</span></td></tr>
            @if ($journal)
                <tr><th>Journal</th><td>@if ($canViewJournal)<a href="{{ route('admin.accounting.journal-entries.show', $journal) }}" style="font-weight:700">{{ $journal->journal_number }}</a>@else{{ $journal->journal_number }}@endif · <x-admin.status-badge :status="$journal->status"/></td></tr>
                <tr><th>Posting date</th><td>{{ $journal->journal_date?->toDateString() }} (payroll period end)</td></tr>
                <tr><th>Debit / Credit total</th><td>{{ number_format($journal->total_debit, 2) }} / {{ number_format($journal->total_credit, 2) }}</td></tr>
                <tr><th>Posted at</th><td>{{ $run->posted_at?->format('Y-m-d H:i') ?? '-' }}</td></tr>
            @else
                <tr><th>Journal</th><td>{{ $run->isApproved() ? 'No journal yet' : 'Created when the approved run is posted' }}</td></tr>
            @endif
            @if ($run->posting_error)
                <tr><th>Posting error</th><td class="warn" style="color:var(--red)">{{ $run->posting_error }}</td></tr>
            @endif
            @if ($run->reversalJournal)
                <tr><th>Reversal</th><td>@if ($canViewJournal)<a href="{{ route('admin.accounting.journal-entries.show', $run->reversalJournal) }}">{{ $run->reversalJournal->journal_number }}</a>@else{{ $run->reversalJournal->journal_number }}@endif · {{ $run->reversed_at?->format('Y-m-d H:i') }} · {{ $run->reversal_reason }}</td></tr>
            @endif
            @if ($canViewJournal && $journal)
                @foreach ($journal->lines as $line)
                    <tr><th class="small">{{ $loop->first ? 'Journal lines' : '' }}</th><td class="small">{{ $line->account?->label() }} · {{ $line->description }} · Dr {{ number_format($line->debit, 2) }} / Cr {{ number_format($line->credit, 2) }}</td></tr>
                @endforeach
            @endif
        </tbody>
        @if ($canPost || $canReverse)
            <x-slot:footer>
                @if ($canPost)
                    <form method="POST" action="{{ route('admin.hr.payroll.post', $run) }}">
                        @csrf
                        <button type="submit" class="btn sm primary">{{ $run->accounting_status === \App\Models\PayrollRun::ACCOUNTING_FAILED ? 'Retry Posting' : 'Post to Accounting' }}</button>
                    </form>
                @endif
                <span class="small">Posting is idempotent: a retry never creates a second journal.{{ $canReverse && $run->isPaid() ? ' Reversing the payroll posting is not offered while a salary payment is recorded: reverse the salary payment first.' : '' }}</span>
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Salary Payment --}}
    <x-admin.data-table title="Salary Payment" subtitle="Settlement of the payroll payable: Dr payroll payable, Cr the chosen Cash / Bank account. One full payment per run; the expense is never posted again and project cost does not move. Bank / WPS file generation is the separate Bank Export section." class="detail-table" id="payment">
        <tbody>
            <tr><th>Payroll payable</th><td>{{ $money($run->net_amount) }}</td></tr>
            <tr><th>Paid</th><td>{{ $money($run->paid_amount) }}</td></tr>
            <tr><th>Remaining</th><td><strong>{{ $money($run->remainingPayable()) }}</strong></td></tr>
            <tr><th>Payment state</th><td><span class="badge {{ $paymentClass }}">{{ $run->paymentLabel() }}</span></td></tr>
            @if ($settlement)
                <tr><th>Payment date</th><td>{{ $settlement->payment_date->toDateString() }} · recorded {{ $settlement->created_at?->format('Y-m-d H:i') }} by {{ $settlement->creator?->name ?? '-' }}</td></tr>
                <tr><th>Payment journal</th><td>@if ($paymentJournal && $canViewJournal)<a href="{{ route('admin.accounting.journal-entries.show', $paymentJournal) }}" style="font-weight:700">{{ $paymentJournal->journal_number }}</a>@else{{ $paymentJournal?->journal_number ?? '-' }}@endif @if ($paymentJournal)· <x-admin.status-badge :status="$paymentJournal->status"/>@endif</td></tr>
                @if ($canViewSettlementFinance)
                    <tr><th>Bank / Cash account</th><td>{{ $settlement->paymentAccount?->label() ?? '-' }} · {{ $settlement->payment_method }}</td></tr>
                    <tr><th>Reference</th><td>{{ $settlement->reference_number ?? '-' }}</td></tr>
                @endif
            @elseif ($settlementRole && $settlementError)
                <tr><th>Why not payable yet</th><td class="small">{{ $settlementError }}</td></tr>
            @endif
            @foreach ($settlements->filter(fn ($s) => $s->isReversed()) as $old)
                <tr><th class="small">Reversed payment</th><td class="small">{{ $old->payment_date->toDateString() }} · SAR {{ number_format($old->amount, 2) }}{{ $canViewSettlementFinance ? ' · '.($old->paymentAccount?->label() ?? '') : '' }} · journal {{ $old->journalEntry?->journal_number }} reversed by {{ $old->reversalJournal?->journal_number }} on {{ $old->reversed_at?->format('Y-m-d') }} — {{ $old->reversal_reason }}</td></tr>
            @endforeach
            @if (! $wholeRun)
                <tr><th>Authorization</th><td class="small">This payroll run includes employees outside your scope; only a user authorized for the whole run can pay or export it.</td></tr>
            @endif
        </tbody>
        @if ($canSettle || $canReverseSettlement)
            <x-slot:footer>
                @if ($canSettle)
                    <form method="POST" action="{{ route('admin.hr.payroll.settle', $run) }}" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
                        @csrf
                        {{-- One-time key so a double click or a retry cannot record this payment twice (F02). --}}
                        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}"/>
                        <div><label for="payment_date">Payment date</label><input id="payment_date" type="date" name="payment_date" class="input" value="{{ old('payment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required/></div>
                        <div><label for="payment_account_id">Pay from</label>
                            <select id="payment_account_id" name="payment_account_id" class="select" required>
                                <option value="">Select Cash / Bank account</option>
                                @foreach ($settlementAccounts as $account)
                                    <option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label for="reference_number">Reference</label><input id="reference_number" name="reference_number" class="input" maxlength="100" value="{{ old('reference_number') }}" placeholder="Bank batch / voucher"/></div>
                        <button type="submit" class="btn sm primary" onclick="return confirm('Record the full salary payment of {{ $money($run->net_amount) }} for {{ $run->code }}?');">Record Salary Payment</button>
                    </form>
                @endif
                @if ($canReverseSettlement)
                    <form method="POST" action="{{ route('admin.hr.payroll.settlement-reverse', $run) }}" onsubmit="var reason = prompt('Reason for reversing this salary payment (kept on the settlement):'); if (!reason) return false; this.reason.value = reason;">
                        @csrf
                        <input type="hidden" name="reason" value=""/>
                        <button type="submit" class="btn sm danger" title="Posts the opposite journal (Dr bank / cash, Cr payroll payable); the run becomes unpaid again">Reverse Salary Payment</button>
                    </form>
                @endif
            </x-slot:footer>
        @endif
    </x-admin.data-table>

    {{-- Bank Export --}}
    @if ($canExport || $canExportRole)
        <x-admin.data-table title="Bank Export" subtitle="Generic payroll bank transfer file (CSV) built from the stored payroll rows and the employees' bank details, frozen when generated. This is not a verified Saudi WPS / Mudad file." class="detail-table" id="export">
            <tbody>
                @if (! $canExport)
                    <tr><th>Not available</th><td class="small">{{ ! $run->isApproved() ? 'The run must be approved before a transfer file can be generated.' : 'This payroll run includes employees outside your scope; only a user authorized for the whole run can export it.' }}</td></tr>
                @else
                    <tr><th>Employees</th><td>{{ $readiness['employees'] }} total · {{ $readiness['bank'] }} bank transfer · {{ $readiness['cash'] }} cash{{ $readiness['other'] ? ' · '.$readiness['other'].' other' : '' }}</td></tr>
                    <tr><th>Net salary total</th><td>{{ $money($readiness['net_total']) }} (bank transfer rows: {{ $money($readiness['bank_total']) }})</td></tr>
                    <tr><th>Readiness</th><td>@if ($readiness['errors'] === [])<span class="badge green">Ready</span>@else<span class="badge red">{{ $readiness['incomplete'] }} employee(s) with incomplete bank details</span>@endif</td></tr>
                    @foreach ($readiness['errors'] as $error)
                        <tr><th class="small">{{ $loop->first ? 'Fix before export' : '' }}</th><td class="small">{{ $error['code'] }} · {{ $error['name'] }} — {{ $error['problem'] }}</td></tr>
                    @endforeach
                    @forelse ($exports as $file)
                        <tr><th class="small">{{ $loop->first ? 'Generated files' : '' }}</th><td class="small"><a href="{{ route('admin.hr.payroll.bank-export.download', [$run, $file->id]) }}" style="font-weight:700">{{ $file->file_name }}</a> · {{ $file->row_count }} employees · {{ $money($file->total_amount) }} · {{ $file->created_at?->format('Y-m-d H:i') }} by {{ $file->generator?->name ?? '-' }} · SHA-256 {{ substr($file->file_hash, 0, 12) }}…</td></tr>
                    @empty
                        <tr><th class="small">Generated files</th><td class="small">None yet.</td></tr>
                    @endforelse
                @endif
            </tbody>
            @if ($canExport)
                <x-slot:footer>
                    @if ($run->isPaid())
                        <span class="small">The run is paid: the file generated before payment stays downloadable; no new version is created.</span>
                    @elseif ($run->reversal_journal_id)
                        <span class="small">The payroll accounting was reversed; no transfer file can be generated.</span>
                    @else
                        <form method="POST" action="{{ route('admin.hr.payroll.bank-export', $run) }}">
                            @csrf
                            <button type="submit" class="btn sm primary" @disabled($readiness['errors'] !== [] || $readiness['rows'] === [])>Generate bank transfer file</button>
                        </form>
                        <span class="small">Each generation freezes a new version; a later change to an employee's bank details never alters an earlier file.</span>
                    @endif
                </x-slot:footer>
            @endif
        </x-admin.data-table>
    @endif

    {{-- Activity --}}
    @if ($activity !== null)
        <x-admin.data-table title="Activity" subtitle="Latest entries that name this run, limited to the users your role may see" id="activity">
            <thead><tr><th>When</th><th>User</th><th>Module</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
                @forelse ($activity as $log)
                    <tr>
                        <td>{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->user_name }}</td>
                        <td>{{ $log->module }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->description }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="table-empty">No activity recorded for this run yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.data-table>
    @endif

    <div class="note">
        Present and leave days are informational; they do not change pay in the current system. GOSI and an official Saudi WPS / Mudad file are not yet operational; the Bank Export is a generic transfer list.
    </div>
@endsection
