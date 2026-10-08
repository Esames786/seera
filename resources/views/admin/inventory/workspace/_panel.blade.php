@php $prefix=\App\Support\Workspace\InventoryWorkspace::prefix($parent); $isItem=$parent instanceof \App\Models\Item; @endphp
<div class="table-card" style="padding:16px">
    <div role="status" aria-live="polite" data-panel-status></div>
    <h2>{{ __('inventory_workspace.'.$panel) }} — {{ $isItem ? $parent->item_code : $parent->code }}</h2>
    <button type="button" class="btn outline" data-workspace-previous hidden>{{ __('workspace.back') }}</button>
    @if($panel==='accounting')
        <p>{{ __('inventory_workspace.inventory_account') }}: {{ $parent->inventoryAccount?->label() ?: __('inventory_workspace.default_account') }}</p>
        <p>{{ __('inventory_workspace.expense_account') }}: {{ $parent->expenseAccount?->label() ?: __('inventory_workspace.default_account') }}</p>
        <p>{{ __('inventory_workspace.valuation') }}: {{ $parent->valuation_method }} · {{ __('inventory_workspace.vat') }}: {{ $parent->vat_applicable ? __('workspace.yes') : __('workspace.no') }}</p>
        <p>{{ __('inventory_workspace.valuation_help') }}</p>
    @elseif($panel==='context')
        @foreach(['project'=>['Projects','admin.master.projects.show'], 'site'=>['Sites','admin.master.sites.show']] as $relation=>$link)
            @if($actor->hasPermission($link[0],'view') && $parent->$relation)<p><a class="btn outline" href="{{ route($link[1],[$parent->$relation,'return_to'=>route($prefix.'.show',$parent,false).'#context']) }}">{{ __('inventory_workspace.'.$relation) }}: {{ $parent->$relation->name }}</a></p>@endif
        @endforeach
        <p class="note">{{ __('inventory_workspace.ownership_fixed') }}</p>
    @else
        @if($panel==='transfers')<p class="note">{{ __('inventory_workspace.transfer_help') }}</p>@endif
        @if($panel==='issues')<p class="note">{{ __('inventory_workspace.issue_help') }}</p>@endif
        @if($panel==='ledger')<p class="note">{{ __('inventory_workspace.ledger_help') }}</p>@endif
        @if($panel==='activity')<p class="note">{{ __('inventory_workspace.activity_help') }}</p>@endif
        <div style="overflow:auto"><table class="data-table"><thead>
        @if($rows->isNotEmpty()) @php $first=\App\Support\Workspace\InventoryWorkspace::row($rows->first(),$panel,$actor); @endphp
            <tr>@foreach($first['cells'] as $label=>$value)<th>{{ __('inventory_workspace.'.$label) }}</th>@endforeach<th>{{ __('workspace.view') }}</th></tr>
        @endif
        </thead><tbody>
        @forelse($rows as $row)
            @php $display=\App\Support\Workspace\InventoryWorkspace::row($row,$panel,$actor); @endphp
            <tr>@foreach($display['cells'] as $value)<td>{{ $value ?? '-' }}</td>@endforeach<td>
            @if($display['document'] && $display['route'])<a class="btn small outline" href="{{ route($display['route'],[$display['document'],'return_to'=>route($prefix.'.show',$parent,false).'#'.$panel]) }}">{{ __('workspace.view') }}</a>@endif
            </td></tr>
        @empty <tr><td class="table-empty">{{ __('inventory_workspace.empty_'.$panel) }}</td></tr> @endforelse
        </tbody></table></div>
        @include('admin.workspace-pagination',['rows'=>$rows])
        @if($panel==='ledger')<a class="btn outline" href="{{ route('admin.inventory.stock-ledger',[$isItem?'item':'warehouse'=>$parent->id]) }}">{{ __('inventory_workspace.global_ledger') }}</a>@endif
        <a class="btn outline" href="{{ route($prefix.'.workspace.panel',[$parent,$panel]) }}">{{ __('inventory_workspace.view_all') }}</a>
    @endif
</div>
