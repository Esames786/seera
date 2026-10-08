@php
    $actor=auth()->user(); $isItem=$parent instanceof \App\Models\Item;
    $prefix=\App\Support\Workspace\InventoryWorkspace::prefix($parent);
    $module=\App\Support\Workspace\InventoryWorkspace::module($parent);
@endphp
<div class="table-card workspace-identity" style="position:sticky;top:0;z-index:2;padding:16px">
    <h2>{{ $isItem ? $parent->item_code : $parent->code }} — {{ $parent->name }}</h2>
    <p>{{ $parent->status }} · {{ __('inventory_workspace.valuation') }}: {{ $parent->valuation_method }}</p>
    @if($isItem)
        <p>{{ $parent->unit?->code }} · {{ $parent->category?->name }} · {{ __('inventory_workspace.vat') }}: {{ $parent->vat_applicable ? __('workspace.yes') : __('workspace.no') }}</p>
    @else
        <p>@if($actor->hasPermission('Projects','view')){{ $parent->project?->name }}@endif · @if($actor->hasPermission('Sites','view')){{ $parent->site?->name }}@endif · {{ $parent->incharge?->name }}</p>
    @endif
    @if($summary)
        <p>{{ __('inventory_workspace.value') }}: SAR {{ number_format($summary['value'],2) }} ·
        @if($isItem)
            {{ __('inventory_workspace.quantity') }}: {{ number_format($summary['quantity'],3) }} {{ $parent->unit?->code }} · {{ __('inventory_workspace.warehouses') }}: {{ $summary['warehouses'] }}
            @if($summary['low']) <span class="badge red">{{ __('inventory_workspace.low') }}</span> @endif
        @else
            {{ __('inventory_workspace.items') }}: {{ $summary['items'] }}
            @foreach($summary['units'] as $unit) <span class="badge blue">{{ number_format($unit->qty,3) }} {{ $unit->unit ?: __('inventory_workspace.unknown_unit') }}</span> @endforeach
        @endif</p>
        <p class="small">{{ __('inventory_workspace.scoped') }}</p>
    @endif
    <a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route($prefix.'.index')) }}">{{ __('workspace.back') }}</a>
    @if(!$manage && $actor->hasPermission($module,'edit'))<a class="btn primary" href="{{ route($prefix.'.edit',[$parent,'return_to'=>\App\Support\SaveAction::returnTo()]) }}">{{ __('workspace.manage') }}</a>@endif
    @if($manage && $actor->hasPermission($module,'view'))<a class="btn outline" href="{{ route($prefix.'.show',[$parent,'return_to'=>\App\Support\SaveAction::returnTo()]) }}">{{ __('workspace.view') }}</a>@endif
</div>
<div data-workspace data-workspace-guard-tabs>
    <nav class="tabs workspace-nav" data-workspace-nav><a class="tab" href="#profile" data-workspace-section="profile">{{ __('workspace.overview') }}</a>
    @foreach($panels as $definition)<a class="tab" href="{{ route($prefix.'.workspace.panel',[$parent,$definition->key]).'#'.$definition->key }}" data-workspace-related="{{ $definition->key }}" data-related-url="{{ route($prefix.'.workspace.panel',[$parent,$definition->key]) }}">{{ __($definition->title) }}</a>@endforeach</nav>
    <div data-workspace-form>
    @if($manage)
        @include($isItem ? 'admin.inventory.items._form' : 'admin.master.warehouses._form')
    @else
        <div class="table-card" style="padding:16px"><h3>{{ __('workspace.readonly') }}</h3>
        <p>{{ $isItem ? $parent->description : $parent->address }}</p>
        @if($isItem)
            <p>{{ __('inventory_workspace.reorder') }}: {{ $parent->reorder_level }} · {{ __('inventory_workspace.minimum') }}: {{ $parent->minimum_stock }} · {{ __('inventory_workspace.maximum') }}: {{ $parent->maximum_stock }}</p>
            @if($actor->hasPermission('Suppliers','view'))<p>{{ __('inventory_workspace.preferred_supplier') }}: {{ $parent->preferredSupplier?->name ?: '-' }}</p>@endif
        @else <p>{{ $parent->branch?->name }}</p>@endif
        <p class="note">{{ __('inventory_workspace.valuation_help') }}</p></div>
    @endif
    </div>
    <x-admin.workspace-host :close-url="\App\Support\SaveAction::cancelUrl(route($prefix.'.index'))"/>
</div>
