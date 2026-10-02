@php
    $data = compact('panel', 'project', 'user', 'returnTo') + ['supplierPurchases' => $supplierPurchases ?? collect(), 'orderReceipts' => $orderReceipts ?? collect(), 'warehouseQuantities' => $warehouseQuantities ?? collect()];
    $columns = \App\Support\Workspace\ProjectPanelTable::columns($panel);
    $empty = match($panel) {
        'site-expenses' => 'No visible Site Expenses for this project. Add an expense and submit its receipt for approval.',
        'customer' => 'No customer is linked. Choose the customer on the Project profile.',
        'sites' => 'No visible locations belong to this project yet. Add a location when authorized.',
        'staff' => 'No visible employees are assigned. Set the current Project on the employee form; each employee has one current project.',
        'warehouses', 'stock' => 'No visible warehouses or positive stock balances for this project. Assign a warehouse to the project; receive stock through Inventory documents.',
        'suppliers' => 'No visible suppliers are linked. Link this project from Supplier Manage or create an authorized project purchase document.',
        'requests', 'orders', 'receipts' => 'No visible project documents yet. Use the existing Inventory purchase workflow and choose this project; a GRN follows its PO.',
        'materials' => 'No materials have been issued to this project yet. Create a Stock Issue from Inventory when materials are consumed.',
        'invoices', 'payments' => 'No visible project invoices or receipts yet. Create an invoice in Accounts Receivable and select this project; receipt entry remains a separate action.',
        'activity' => 'No securely linked activity yet. New project and location saves appear here. Older name-only logs cannot be reliably assigned and are omitted.',
        default => '',
    };
@endphp
<div class="table-card" data-project-panel="{{ $panel }}">
    <div class="table-title"><strong>{{ $title }} — {{ $project->code }}</strong>
        <a class="btn outline sm" href="{{ route('admin.master.projects.workspace.panel', [$project, $panel]) }}">View all (paged)</a>
        @if($panel === 'sites' && $user->hasPermission('Sites', 'create') && in_array($user->effectiveAccessScope(), ['company', 'project']))
            <a class="btn primary sm" href="{{ route('admin.master.sites.project.create', [$project, 'return_to' => $returnTo]) }}">+ Add Location</a>
        @endif
    </div>
    <div data-panel-status role="status" aria-live="polite"></div>
    @if($panel === 'site-expenses' && $user->hasPermission('Site Expenses', 'create'))<a class="btn primary sm" href="{{ route('admin.site-expenses.create', ['project_id' => $project->id]) }}">Add Site Expense</a>@endif
    @isset($materialTotal)<p class="note">Material used: SAR {{ number_format($materialTotal, 2) }}. Posted issue-ledger values only; receipts are not consumption. Quantities stay per item and unit, not one mixed-unit total.</p>@endisset
    @isset($materialQuantity)<p class="note">Total issued quantity (one shared unit): {{ number_format($materialQuantity, 3) }} {{ $materialUnit }}.</p>@endisset
    @isset($stockTotal)<p class="note">Current positive stock value: SAR {{ number_format($stockTotal, 2) }}. Stored inventory values; quantities are shown per item and unit.</p>@endisset
    @isset($arTotal)<p class="note">Project approved invoices: SAR {{ number_format($arTotal, 2) }}. Amount still to receive: SAR {{ number_format($arOutstanding, 2) }}.</p>@endisset
    @if($panel === 'finance')
        <table><tbody>
            @foreach(['budget' => 'Master budget', 'cost' => 'Posted cost', 'budget_used' => 'Budget used (%)', 'revenue' => 'Posted revenue', 'billed' => 'Supplier billed', 'invoiced' => 'Customer invoiced', 'margin' => 'Margin (posted revenue minus posted cost)'] as $key => $label)
                <tr><th>{{ $label }}</th><td>{{ $key === 'budget_used' ? '' : 'SAR ' }}{{ number_format($finance[$key], $key === 'budget_used' ? 1 : 2) }}</td></tr>
            @endforeach
        </tbody></table>
        <p class="note">All dates; same scoped calculations as Project Cost Report. Posted cost is net debit minus credit on expense-account journal lines carrying this Project. Revenue is net credit minus debit on revenue-account lines. Reversals net off. Billed/invoiced follow the report's non-draft document semantics (including cancelled documents); they are not cash or approved-only AR totals. Cost centres alone do not assign a Project.</p>
        <p class="note">Posted Site Expenses contribute through journal lines only. Supplier Credit contributes through its linked posted Supplier Bill, never twice. Payroll/labour-to-GL and equipment cost remain outside this phase. Budget is a master amount, not BOQ/budget lines; it is not a site-specific budget.</p>
        <a class="btn outline" href="{{ route('admin.accounting.reports.project-cost-report', ['project' => $project->id]) }}">Open Project Cost Report</a>
    @else
        <div style="overflow-x:auto"><table>
            <thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach<th>Actions</th></tr></thead>
            <tbody>
                @forelse($rows as $row)
                    @php $display = \App\Support\Workspace\ProjectPanelTable::row($row, $data); @endphp
                    <tr>@foreach($display['cells'] as $cell)<td>{{ $cell ?? '-' }}</td>@endforeach
                        <td><div class="actions">@foreach($display['actions'] as $action)<a class="btn sm outline" href="{{ $action['url'] }}">{{ $action['label'] }}</a>@endforeach</div></td>
                    </tr>
                @empty <tr><td colspan="{{ count($columns) + 1 }}" class="table-empty">{{ $empty }}</td></tr>@endforelse
            </tbody>
        </table></div>
        <div class="table-footer">
            <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }}</span>
            @if(request()->wantsJson())
                @if($rows->previousPageUrl())<button type="button" class="btn outline" data-panel-load="{{ $rows->previousPageUrl() }}">Previous</button>@endif
                @if($rows->nextPageUrl())<button type="button" class="btn outline" data-panel-load="{{ $rows->nextPageUrl() }}">Next</button>@endif
            @else {{ $rows->links() }} @endif
        </div>
        @if($panel === 'materials' && $user->hasPermission('Inventory Reports', 'view'))<a class="btn outline" href="{{ route('admin.inventory.reports.project-consumption', ['project' => $project->id]) }}">Open Project Consumption Report</a>@endif
        @if($panel === 'invoices')<p class="note">ZATCA state is local foundation information, not a claim of live clearance.</p>@endif
        @if($panel === 'activity')<p class="note">Only exact Project entity-token activity visible to your role is included. Legacy free-text/name-only history is omitted to avoid false matches.</p>@endif
    @endif
</div>
