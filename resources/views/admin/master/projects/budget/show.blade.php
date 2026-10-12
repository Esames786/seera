@extends('layouts.admin')

@section('title', 'Project Budget / BOQ')
@section('breadcrumb', 'Master Setup / Projects / Budget / BOQ')

@section('content')
    @php
        /** @var \App\Models\Project $project */
        /** @var \App\Models\ProjectBudget|null $budget */
        $money = fn ($v) => $v === null ? '—' : 'SAR '.number_format((float) $v, 2);
        $categories = \App\Models\ProjectBudgetLine::CATEGORIES;
        $selfUrl = $budget ? route('admin.master.projects.budget.show', [$project, 'version' => $budget->version], false) : route('admin.master.projects.budget.show', $project, false);
        $statusClass = fn ($b) => $b->isCurrent() ? 'green' : ($b->isDraft() ? 'yellow' : ($b->status === \App\Models\ProjectBudget::STATUS_DISCARDED ? 'red' : 'gray'));
        $isDraft = $budget?->isDraft() ?? false;
        $sections = ['summary' => 'Budget Summary', 'lines' => 'Budget Lines', 'bva' => 'Budget vs Actual', 'versions' => 'Versions / Revisions'];
        if ($activity !== null) $sections['activity'] = 'Activity';
        $totals = $report['totals'] ?? null;
        $hasDraft = $versions->firstWhere('status', \App\Models\ProjectBudget::STATUS_DRAFT) !== null;
    @endphp

    <x-admin.page-header :title="'Budget / BOQ: '.$project->code.' / '.$project->name" description="Planning layer: BOQ lines per version, the current budget and Budget vs Actual. Budgets never post to accounting or move stock; actual cost comes from the posted ledger (same semantics as the Project Cost Report).">
        @if ($budget && $isDraft && $canFinalize)
            <form method="POST" action="{{ route('admin.master.projects.budget.finalize', [$project, $budget->id]) }}" onsubmit="return confirm('Set {{ $budget->reference }} as the current budget? The draft becomes read-only and the previous current version is superseded.');">
                @csrf
                <button type="submit" class="btn primary">Set as Current Budget</button>
            </form>
        @endif
        @if ($budget && $budget->isCurrent() && $canFinalize && ! $hasDraft)
            <form method="POST" action="{{ route('admin.master.projects.budget.revise', [$project, $budget->id]) }}">
                @csrf
                <button type="submit" class="btn primary" title="Copies the current lines into a new draft version">Create Revision</button>
            </form>
        @endif
        @if ($canEdit && ! $hasDraft && $versions->firstWhere('is_current', true) === null)
            <form method="POST" action="{{ route('admin.master.projects.budget.store', $project) }}">
                @csrf
                <button type="submit" class="btn primary">Create Detailed Budget</button>
            </form>
        @endif
        @if ($budget && $isDraft && $canDelete)
            <form method="POST" action="{{ route('admin.master.projects.budget.destroy', [$project, $budget->id]) }}" onsubmit="return confirm('Discard draft {{ $budget->reference }}? It stays in the version history as read-only; current and superseded versions are kept.');">
                @csrf @method('DELETE')
                <button type="submit" class="btn danger">Discard Draft</button>
            </form>
        @endif
        @if ($budget && $canExport)
            <a class="btn outline" href="{{ route('admin.master.projects.budget.export', [$project, $budget->id, 'site' => $siteId]) }}">Export CSV</a>
        @endif
        <a class="btn outline" href="{{ route('admin.master.projects.show', $project) }}">Back to Project</a>
    </x-admin.page-header>

    {{-- Persistent identity --}}
    <div class="card workspace-header" data-workspace-header>
        <div class="identity">
            <h2>{{ $project->code }} / {{ $project->name }}</h2>
            <div>
                @if ($budget)
                    <span class="badge {{ $statusClass($budget) }}">{{ $budget->reference }} · v{{ $budget->version }} · {{ $budget->statusLabel() }}</span>
                @else
                    <span class="badge gray">No detailed budget yet</span>
                @endif
                @if ($totals && $canFinance && $totals['over_budget'])<span class="badge red">Over budget</span>@endif
            </div>
        </div>
        <dl>
            <dt>Customer / Manager</dt><dd>{{ $project->customer?->name ?? 'Not assigned' }} / {{ $project->manager?->name ?? 'Not assigned' }}</dd>
            <dt>Master budget (profile)</dt><dd>SAR {{ number_format((float) $project->budget, 2) }}</dd>
            <dt>Detailed current budget</dt><dd>{{ $project->currentBudget ? 'SAR '.number_format((float) $project->currentBudget->total_amount, 2).' ('.$project->currentBudget->reference.')' : 'none yet' }}</dd>
            @if ($budget)<dt>This version</dt><dd>{{ $budget->title }} · {{ $budget->line_count }} lines · SAR {{ number_format((float) $budget->total_amount, 2) }}</dd>@endif
        </dl>
        <dl>
            @if ($canFinance && $totals)
                <dt>Actual cost (posted)</dt><dd>{{ $money($totals['actual']) }}</dd>
                <dt>Committed (open POs)</dt><dd>{{ $money($totals['committed']) }}</dd>
                <dt>Remaining budget</dt><dd><strong>{{ $money($totals['remaining']) }}</strong>{{ $totals['over_budget'] ? ' · Over budget' : '' }}</dd>
                <dt>Utilization</dt><dd>{{ $totals['utilization'] === null ? '—' : number_format($totals['utilization'], 1).'%' }}</dd>
            @elseif ($budget)
                <dt>Actual / Committed</dt><dd class="small">Financial values are shown to users with Financial Reports view.</dd>
            @endif
        </dl>
    </div>

    @if (! $budget)
        <div class="card" style="padding:24px">
            <h3>{{ $versions->isEmpty() ? 'No detailed budget has been created for this project yet.' : 'No active budget version: every version was discarded.' }}</h3>
            <p>Create a draft version, add BOQ lines (cost code, category, site, item, quantity × rate or a lump sum, expense account), then Set as Current. The master budget on the project profile stays as it is.</p>
            @if ($canEdit)
                <form method="POST" action="{{ route('admin.master.projects.budget.store', $project) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end">
                    @csrf
                    <div><label for="title">Title</label><input id="title" name="title" class="input" maxlength="150" value="{{ old('title') }}" placeholder="Detailed budget v1"/></div>
                    <button type="submit" class="btn primary">Create Detailed Budget</button>
                </form>
            @else
                <p class="small">A user with Projects — edit creates the first version.</p>
            @endif
        </div>
    @else
        <nav class="tabs workspace-nav" aria-label="Budget sections">
            @foreach ($sections as $key => $label)
                <a class="tab" href="#{{ $key }}">{{ $label }}</a>
            @endforeach
        </nav>

        {{-- Budget Summary --}}
        <div class="card-grid" id="summary">
            <x-admin.metric-card color="blue" :value="'SAR '.number_format((float) $budget->total_amount, 2)" label="Budget (this version)"/>
            @if ($canFinance && $totals)
                <x-admin.metric-card color="cyan" :value="$money($totals['committed'])" label="Committed (open POs)"/>
                <x-admin.metric-card color="green" :value="$money($totals['actual'])" label="Actual (posted)"/>
                <x-admin.metric-card :color="$totals['over_budget'] ? 'red' : 'green'" :value="$money($totals['remaining'])" :label="$totals['over_budget'] ? 'Over budget' : 'Remaining budget'"/>
            @else
                <x-admin.metric-card color="gray" :value="$budget->line_count" label="Budget lines"/>
            @endif
        </div>

        <x-admin.data-table title="Budget by category" :subtitle="$canFinance ? 'Budget = Σ lines · Actual = posted expense lines of this project on the accounts mapped by the lines · Committed = unreceived value of approved purchase orders · Remaining = Budget − Actual · Variance = Budget − Actual − Committed' : 'Budget = Σ lines of this version'" class="detail-table">
            <thead><tr><th>Category</th><th>Lines</th><th>Budget</th>@if ($canFinance)<th>Committed</th><th>Actual</th><th>Remaining</th><th>Variance</th><th>Status</th>@endif</tr></thead>
            <tbody>
                @forelse ($report['categories'] as $row)
                    <tr>
                        <td><strong>{{ \App\Models\ProjectBudgetLine::categoryLabel($row['category']) }}</strong>@if ($row['integration'])<div class="small" style="color:var(--red)">{{ $row['integration'] }}</div>@endif</td>
                        <td>{{ $row['lines'] }}</td>
                        <td>{{ number_format($row['budget'], 2) }}</td>
                        @if ($canFinance)
                            <td>{{ $row['committed'] === null ? '—' : number_format($row['committed'], 2) }}</td>
                            <td>{{ $row['actual'] === null ? 'No account mapped' : number_format($row['actual'], 2) }}</td>
                            <td>{{ $row['remaining'] === null ? '—' : number_format($row['remaining'], 2) }}</td>
                            <td>{{ $row['variance'] === null ? '—' : number_format($row['variance'], 2) }}</td>
                            <td><span class="badge {{ $row['status'] === 'Over budget' ? 'red' : ($row['status'] === 'Within budget' ? 'green' : 'gray') }}">{{ $row['status'] }}</span></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canFinance ? 8 : 3 }}" class="table-empty">No budget lines yet. Add lines below.</td></tr>
                @endforelse
            </tbody>
            @if ($canFinance && $totals)
                <x-slot:footer>
                    <span class="small">Total actual SAR {{ number_format($totals['actual'], 2) }} equals the Project Cost Report posted cost for this project{{ $siteId ? ' (site filter applied)' : '' }}; cost on accounts no line maps to is listed under Budget vs Actual as "Other posted cost".</span>
                </x-slot:footer>
            @endif
        </x-admin.data-table>

        {{-- Budget Lines --}}
        <x-admin.data-table title="Budget Lines" :subtitle="$budget->reference.' · '.$budget->statusLabel().($isDraft ? ' · editable' : ' · read-only')" id="lines">
            <x-slot:headerActions>
                <form method="GET" action="{{ route('admin.master.projects.budget.show', $project) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                    <input type="hidden" name="version" value="{{ $budget->version }}"/>
                    <select name="site" class="select" style="width:auto"><option value="">All sites</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected($siteId === $site->id)>{{ $site->name }}</option>@endforeach</select>
                    <select name="category" class="select" style="width:auto"><option value="">All categories</option>@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach</select>
                    <input name="cost_code" class="input" style="width:120px" placeholder="Cost code" value="{{ $costCodeFilter }}"/>
                    <button type="submit" class="btn sm outline">Filter</button>
                </form>
            </x-slot:headerActions>
            <thead>
                <tr><th>Cost code</th><th>Category</th><th>Description</th><th>Site</th><th>Item</th><th>Unit</th><th>Qty</th><th>Rate</th><th>Amount</th><th>Cost account</th><th>Issued to project</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($lines as $line)
                    @php $issued = $line->item_id ? ($report['issued'][$line->item_id] ?? null) : null; @endphp
                    <tr>
                        <td><strong>{{ $line->cost_code }}</strong></td>
                        <td>{{ \App\Models\ProjectBudgetLine::categoryLabel($line->category) }}</td>
                        <td>{{ $line->description }}@if ($line->notes)<div class="small">{{ $line->notes }}</div>@endif</td>
                        <td>{{ $line->site?->name ?? 'Whole project' }}</td>
                        <td>{{ $line->item?->label() ?? '-' }}</td>
                        <td>{{ $line->unit?->name ?? $line->item?->unit?->name ?? '-' }}</td>
                        <td>{{ $line->isLumpSum() ? 'Lump sum' : number_format($line->quantity, 3) }}</td>
                        <td>{{ $line->isLumpSum() ? '-' : number_format($line->rate, 4) }}</td>
                        <td><strong>{{ number_format($line->amount, 2) }}</strong></td>
                        <td class="small">{{ $line->account?->label() ?? 'Not mapped (planning only)' }}</td>
                        <td class="small">@if ($issued === null){{ $line->item_id ? 'Nothing issued yet' : '-' }}@else{{ number_format($issued['quantity'], 3) }} {{ $line->item?->unit?->name }} · {{ $canFinance ? 'SAR '.number_format($issued['value'], 2) : '' }}@endif</td>
                        <td>
                            @if ($isDraft && $canEdit)
                                <div class="actions">
                                    <a class="btn sm outline" href="{{ route('admin.master.projects.budget.lines.edit', [$project, $budget->id, $line->id]) }}">Edit</a>
                                    <form method="POST" action="{{ route('admin.master.projects.budget.lines.copy', [$project, $budget->id, $line->id]) }}">@csrf<button type="submit" class="btn sm outline">Copy</button></form>
                                    <form method="POST" action="{{ route('admin.master.projects.budget.lines.destroy', [$project, $budget->id, $line->id]) }}" onsubmit="return confirm('Remove line {{ $line->cost_code }} from the draft?');">@csrf @method('DELETE')<button type="submit" class="btn sm danger">Remove</button></form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="table-empty">{{ $lines->total() === 0 && ! $siteId && ! request('category') && $costCodeFilter === '' ? 'No BOQ lines in this version yet.'.($isDraft && $canEdit ? ' Add the first line below.' : '') : 'No lines match the filter.' }}</td></tr>
                @endforelse
            </tbody>
            <x-slot:footer>
                <span class="small">Showing {{ $lines->firstItem() ?? 0 }}-{{ $lines->lastItem() ?? 0 }} of {{ $lines->total() }} · Amount = quantity × rate (server-calculated) or a lump sum</span>
                @if ($lines->hasPages()){{ $lines->links() }}@endif
            </x-slot:footer>
        </x-admin.data-table>

        @if ($isDraft && $canEdit)
            <div class="card" style="padding:16px;margin-bottom:16px" id="add-line">
                <h3>Add Line</h3>
                @if ($errors->any())<div class="alert">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('admin.master.projects.budget.lines.store', [$project, $budget->id]) }}" class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;align-items:end">
                    @csrf
                    <div><label for="cost_code">Cost code *</label><input id="cost_code" name="cost_code" class="input" maxlength="32" required value="{{ old('cost_code') }}" placeholder="MAT-001"/></div>
                    <div><label for="category">Category *</label><select id="category" name="category" class="select" required>@foreach ($categories as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div style="grid-column:span 2"><label for="description">Description *</label><input id="description" name="description" class="input" maxlength="200" required value="{{ old('description') }}" placeholder="Reinforcement steel 16mm"/></div>
                    <div><label for="site_id">Site</label><select id="site_id" name="site_id" class="select"><option value="">Whole project</option>@foreach ($sites as $site)<option value="{{ $site->id }}" @selected((string) old('site_id') === (string) $site->id)>{{ $site->name }}</option>@endforeach</select></div>
                    <div><label for="item_id">Item (materials)</label><select id="item_id" name="item_id" class="select"><option value="">None</option>@foreach ($items as $item)<option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->item_code }} - {{ $item->name }}</option>@endforeach</select></div>
                    <div><label for="unit_id">Unit</label><select id="unit_id" name="unit_id" class="select"><option value="">From item / none</option>@foreach ($units as $unit)<option value="{{ $unit->id }}" @selected((string) old('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>@endforeach</select></div>
                    <div><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" step="0.001" min="0" class="input" value="{{ old('quantity') }}"/></div>
                    <div><label for="rate">Rate (SAR)</label><input id="rate" name="rate" type="number" step="0.0001" min="0" class="input" value="{{ old('rate') }}"/></div>
                    <div><label for="amount">Lump sum (SAR)</label><input id="amount" name="amount" type="number" step="0.01" min="0" class="input" value="{{ old('amount') }}" placeholder="when no qty × rate"/></div>
                    <div style="grid-column:span 2"><label for="chart_of_account_id">Cost account (for Budget vs Actual)</label><select id="chart_of_account_id" name="chart_of_account_id" class="select"><option value="">Not mapped — planning only</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((string) old('chart_of_account_id') === (string) $account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
                    <div style="grid-column:span 2"><label for="notes">Notes</label><input id="notes" name="notes" class="input" maxlength="1000" value="{{ old('notes') }}"/></div>
                    <div><button type="submit" class="btn primary">Add Line</button></div>
                </form>
                <p class="small">Amount is calculated on the server as quantity × rate; leave both empty for a lump-sum line. One cost account belongs to one cost code within a version, so posted cost is never counted twice.</p>
            </div>
        @endif

        {{-- Budget vs Actual --}}
        <x-admin.data-table title="Budget vs Actual" :subtitle="$canFinance ? 'By cost code. Actual = net posted expense on the mapped accounts (Project Cost Report semantics); Committed = unreceived value of approved purchase orders bucketed by the item\'s expense account; Remaining = Budget − Actual; Variance = Budget − Actual − Committed.' : 'Financial comparison is shown to users with Financial Reports view.'" id="bva">
            <thead><tr><th>Category</th><th>Cost code</th><th>Description</th><th>Sites</th><th>Accounts</th><th>Budget</th>@if ($canFinance)<th>Committed</th><th>Actual</th><th>Remaining</th><th>Variance</th><th>Used</th><th>Status</th>@endif</tr></thead>
            <tbody>
                @forelse ($report['rows'] as $row)
                    <tr>
                        <td>{{ \App\Models\ProjectBudgetLine::categoryLabel($row['category']) }}</td>
                        <td><strong>{{ $row['cost_code'] }}</strong></td>
                        <td>{{ $row['description'] }}</td>
                        <td class="small">{{ $row['sites'] === [] ? 'Whole project' : implode(', ', $row['sites']) }}</td>
                        <td class="small">{{ $row['accounts'] === [] ? 'Not mapped' : implode(', ', $row['accounts']) }}</td>
                        <td>{{ number_format($row['budget'], 2) }}</td>
                        @if ($canFinance)
                            <td>{{ $row['committed'] === null ? '—' : number_format($row['committed'], 2) }}</td>
                            <td>{{ $row['actual'] === null ? '—' : number_format($row['actual'], 2) }}</td>
                            <td>{{ $row['remaining'] === null ? '—' : number_format($row['remaining'], 2) }}</td>
                            <td>{{ $row['variance'] === null ? '—' : number_format($row['variance'], 2) }}</td>
                            <td>{{ $row['utilization'] === null ? '—' : number_format($row['utilization'], 1).'%' }}</td>
                            <td><span class="badge {{ $row['status'] === 'Over budget' ? 'red' : ($row['status'] === 'Within budget' ? 'green' : ($row['status'] === 'Committed beyond budget' ? 'yellow' : 'gray')) }}">{{ $row['status'] }}</span></td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canFinance ? 12 : 6 }}" class="table-empty">No budget lines to compare yet.</td></tr>
                @endforelse
                @if ($canFinance && ($report['unmapped']['actual'] != 0 || $report['unmapped']['committed'] != 0))
                    <tr>
                        <td>Other</td><td>—</td><td>Posted cost / commitments on accounts no budget line maps to</td><td class="small">—</td>
                        <td class="small">{{ implode(', ', $report['unmapped']['accounts']) }}</td>
                        <td>0.00</td><td>{{ number_format($report['unmapped']['committed'], 2) }}</td><td>{{ number_format($report['unmapped']['actual'], 2) }}</td><td>—</td><td>—</td><td>—</td><td><span class="badge gray">Not budgeted</span></td>
                    </tr>
                @endif
                @if ($canFinance && $totals)
                    <tr>
                        <th colspan="5">Total</th>
                        <th>{{ number_format($totals['budget'], 2) }}</th><th>{{ number_format($totals['committed'], 2) }}</th><th>{{ number_format($totals['actual'], 2) }}</th>
                        <th>{{ number_format($totals['remaining'], 2) }}</th><th>{{ number_format($totals['variance'], 2) }}</th><th>{{ $totals['utilization'] === null ? '—' : number_format($totals['utilization'], 1).'%' }}</th>
                        <th><span class="badge {{ $totals['over_budget'] ? 'red' : 'green' }}">{{ $totals['over_budget'] ? 'Over budget' : 'Within budget' }}</span></th>
                    </tr>
                @endif
            </tbody>
            @if ($canFinance)
                <x-slot:footer>
                    <span class="small">Drill-down: </span>
                    <a class="btn sm outline" href="{{ route('admin.master.projects.workspace.panel', [$project, 'materials']) }}">Material Used (Stock Issues)</a>
                    <a class="btn sm outline" href="{{ route('admin.master.projects.workspace.panel', [$project, 'orders']) }}">Project Purchases (POs)</a>
                    <a class="btn sm outline" href="{{ route('admin.master.projects.workspace.panel', [$project, 'site-expenses']) }}">Site Expenses</a>
                    @if (auth()->user()->hasPermission('Accounts Payable', 'view'))<a class="btn sm outline" href="{{ route('admin.master.projects.workspace.panel', [$project, 'finance']) }}">Supplier Bills</a>@endif
                    @if (auth()->user()->hasPermission('Payroll', 'view'))<a class="btn sm outline" href="{{ route('admin.hr.payroll.index') }}">Payroll runs</a>@endif
                    <a class="btn sm outline" href="{{ route('admin.accounting.reports.project-cost-report', ['project' => $project->id]) }}">Project Cost Report</a>
                    @if (auth()->user()->hasPermission('Equipment', 'view'))<a class="btn sm outline" href="{{ route('admin.master.projects.workspace.panel', [$project, 'equipment']) }}">Equipment (fuel / maintenance)</a>@endif
                    <span class="small">Equipment actual = posted fuel / maintenance cost on the mapped accounts (an operational log counts only once Finance posts it); live GPS usage costing is not included.</span>
                </x-slot:footer>
            @endif
        </x-admin.data-table>

        {{-- Versions --}}
        <x-admin.data-table title="Versions / Revisions" subtitle="A draft is editable; Set as Current freezes it and supersedes the previous current version; Create Revision copies the current lines into a new draft; a discarded draft stays listed read-only. History is never deleted." id="versions">
            <thead><tr><th>Version</th><th>Reference</th><th>Title</th><th>Status</th><th>Lines</th><th>Total</th><th>Based on</th><th>Created</th><th>Set current</th><th></th></tr></thead>
            <tbody>
                @foreach ($versions as $v)
                    <tr @if ($v->id === $budget->id) style="font-weight:700" @endif>
                        <td>v{{ $v->version }}</td>
                        <td>{{ $v->reference }}</td>
                        <td>{{ $v->title }}</td>
                        <td><span class="badge {{ $statusClass($v) }}">{{ $v->statusLabel() }}</span></td>
                        <td>{{ $v->line_count }}</td>
                        <td>SAR {{ number_format((float) $v->total_amount, 2) }}</td>
                        <td>{{ $v->basedOn?->reference ?? '-' }}</td>
                        <td class="small">{{ $v->created_at?->format('Y-m-d H:i') }} · {{ $v->creator?->name ?? '-' }}</td>
                        <td class="small">{{ $v->finalized_at ? $v->finalized_at->format('Y-m-d H:i').' · '.($v->finalizer?->name ?? '-') : '-' }}{{ $v->superseded_at ? ' · superseded '.$v->superseded_at->format('Y-m-d') : '' }}</td>
                        <td><a class="btn sm outline" href="{{ route('admin.master.projects.budget.show', [$project, 'version' => $v->version]) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin.data-table>

        {{-- Activity --}}
        @if ($activity !== null)
            <x-admin.data-table title="Activity" subtitle="Budget events of this project, limited to the users your role may see" id="activity">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
                <tbody>
                    @forelse ($activity as $log)
                        <tr><td>{{ $log->created_at?->format('Y-m-d H:i') }}</td><td>{{ $log->user_name }}</td><td>{{ $log->action }}</td><td>{{ $log->description }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="table-empty">No budget activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        @endif
    @endif

    <div class="note">
        Budget is planning, not accounting: creating, editing or setting a budget as current never creates a journal entry, bill, invoice, stock movement or payroll change. Budget multi-step approval, equipment actual costing and project scheduling are NOT YET OPERATIONAL.
    </div>
@endsection
