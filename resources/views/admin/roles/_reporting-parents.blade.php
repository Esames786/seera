@if($role->parent)
    <div>{{ __('ui.primary_parent') }}: {{ $role->parent->name }}</div>
@endif
@foreach($role->additionalParents as $reportingParent)
    <div>{{ __('ui.additional_parents') }}: {{ $reportingParent->name }}</div>
@endforeach
@if(!$role->parent && $role->additionalParents->isEmpty()) — @endif
