@php $panels=\App\Support\Workspace\SiteWorkspacePanels::class; $origin=route('admin.master.sites.show',$site,false).'#'.$panel; @endphp
<div class="table-card" style="padding:16px"><h2>{{ __('workspace.'.$panel) }} — {{ $site->code }}</h2><div data-panel-status role="status"></div><x-admin.workspace-previous/>
@if($panel === 'location')
    <p class="note">{{ __('workspace.geofence_help') }}</p>
    @foreach(['latitude','longitude','geofence_radius','geofence_enabled','attendance_inside_only','offline_attendance_allowed'] as $field)<p>{{ __('workspace.'.$field) }}: {{ is_bool($site->$field) ? ($site->$field ? __('workspace.yes'):__('workspace.no')) : ($site->$field ?? '-') }}</p>@endforeach
    <a class="btn outline" href="{{ route('admin.master.sites.show',$site) }}#profile">{{ __('workspace.map_overview') }}</a>
@else
    @if($panel === 'attendance')<p class="note">{{ __('workspace.attendance_help') }}</p>@endif
    @if($panel === 'materials')<p class="note">{{ __('workspace.material_help') }}</p>@endif
    @if($panel === 'receipts')<p class="note">{{ __('workspace.receipts_help') }}</p>@endif
    @if($panel === 'activity')<p class="note">{{ __('workspace.site_activity_help') }}</p>@endif
    @if($panel === 'expenses' && $actor->hasPermission('Site Expenses','create'))
        <a class="btn primary" href="{{ route('admin.master.sites.expenses.create',$site) }}">{{ __('workspace.add_expense') }}</a>
    @endif
    @php $register = match($panel) { 'project'=>'admin.master.projects.index','staff'=>'admin.hr.employees.index','warehouses'=>'admin.master.warehouses.index','requests'=>'admin.inventory.purchase-requests.index','orders'=>'admin.inventory.purchase-orders.index','receipts'=>'admin.inventory.goods-receipts.index','materials'=>'admin.inventory.stock-issues.index','expenses'=>'admin.site-expenses.index','attendance'=>'admin.hr.attendance.index',default=>null }; @endphp
    @if($register)<a class="btn outline" href="{{ route($register, $panel === 'expenses' ? ['site_id'=>$site->id,'project_id'=>$site->project_id] : []) }}">{{ __('workspace.open_register') }}</a>@endif
    <div style="overflow-x:auto"><table><thead><tr>@foreach($panels::columns($panel,$actor) as $column)<th>{{ __('workspace.'.$column) }}</th>@endforeach<th>{{ __('workspace.actions') }}</th></tr></thead><tbody>
    @forelse($rows as $row)
    <tr>@foreach($panels::cells($row,$panel,$actor) as $cell)<td>{{ $cell ?? '-' }}</td>@endforeach<td>
        @if($route=$panels::route($panel))<a class="btn sm outline" href="{{ route($route,[$panel === 'materials' ? $row->reference_id : $row->id,'return_to'=>$origin]) }}">{{ __('workspace.view') }}</a>@endif
        @if(in_array($panel,['project','staff']) && $actor->hasPermission($panel === 'project' ? 'Projects':'HR','edit'))
        <a class="btn sm outline" href="{{ route($panel === 'project' ? 'admin.master.projects.edit':'admin.hr.employees.edit',[$row->id,'return_to'=>$origin]) }}">{{ __('workspace.manage') }}</a>@endif
        @if($panel === 'warehouses')
            @if($actor->hasPermission('Warehouse Stock','view'))<a class="btn sm outline" href="{{ route('admin.inventory.stock.index',['warehouse'=>$row->id]) }}">{{ __('workspace.stock') }}</a>@endif
            @if($actor->hasPermission('Stock Ledger','view'))<a class="btn sm outline" href="{{ route('admin.inventory.stock-ledger',['warehouse'=>$row->id]) }}">{{ __('workspace.ledger') }}</a>@endif
        @endif
    </td></tr>
    @empty <tr><td colspan="12">{{ __('workspace.empty') }}</td></tr>@endforelse
    </tbody></table></div>
    @include('admin.workspace-pagination')
@endif
</div>
