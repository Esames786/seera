@php $actor=auth()->user(); @endphp
<div class="table-card workspace-identity" style="position:sticky;top:0;z-index:2;padding:16px">
<h2>{{ $site->code }} — {{ $site->name }}</h2>
<p>{{ __('workspace.project') }}: {{ $actor->hasPermission('Projects','view') ? ($site->project?->name ?: '-') : '#' . $site->project_id }} · {{ __('workspace.manager') }}: {{ $site->supervisor?->name ?: '-' }} · {{ $site->status }}</p>
<p>{{ $site->address }} · {{ $site->latitude }}, {{ $site->longitude }} · {{ __('workspace.radius') }}: {{ $site->geofence_radius }} m</p>
<p>{{ __('workspace.geofence') }}: {{ $site->geofence_enabled ? __('workspace.yes'):__('workspace.no') }} · {{ __('workspace.inside_only') }}: {{ $site->attendance_inside_only ? __('workspace.yes'):__('workspace.no') }}</p>
@foreach($summary as $label=>$count)<span class="badge blue">{{ __('workspace.'.$label) }}: {{ $count }}</span>@endforeach
<a class="btn outline" href="{{ \App\Support\SaveAction::cancelUrl(route('admin.master.sites.index')) }}">{{ __('workspace.back_sites') }}</a>
@if(!$manage && $actor->hasPermission('Sites','edit'))<a class="btn primary" href="{{ route('admin.master.sites.edit',[$site,'return_to'=>\App\Support\SaveAction::returnTo()]) }}">{{ __('workspace.manage') }}</a>@endif
@if($manage && $actor->hasPermission('Sites','view'))<a class="btn outline" href="{{ route('admin.master.sites.show',$site) }}">{{ __('workspace.view') }}</a>@endif
</div>
<div data-workspace data-workspace-guard-tabs>
<nav class="tabs workspace-nav" data-workspace-nav><a class="tab" href="#profile" data-workspace-section="profile">{{ __('workspace.overview') }}</a>
@foreach($panels as $key=>$definition)<a class="tab" href="{{ route('admin.master.sites.workspace.panel',[$site,$key]).'#'.$key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.master.sites.workspace.panel',[$site,$key]) }}">{{ __($definition->title) }}</a>@endforeach
</nav>
<div data-workspace-form>
@if($manage) @include('admin.master.sites._form')
@else <div class="table-card" style="padding:16px"><h3>{{ __('workspace.readonly') }}</h3><p>{{ $site->address }}</p><p class="note">{{ __('workspace.geofence_help') }}</p><x-admin.site-map :lat="$site->latitude" :lng="$site->longitude" :radius="$site->geofence_radius"/></div> @endif
</div>
<x-admin.workspace-host :close-url="\App\Support\SaveAction::cancelUrl(route('admin.master.sites.index'))"/>
</div>
