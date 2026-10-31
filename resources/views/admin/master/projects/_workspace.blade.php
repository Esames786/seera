@include('admin.master.projects._workspace-header')
<div data-workspace>
    <nav class="tabs workspace-nav" data-workspace-nav aria-label="{{ __('Project sections') }}">
        <a class="tab" href="#profile" data-workspace-section="profile">{{ __('Overview') }}</a>
        @if(auth()->user()->hasPermission('Projects', 'view'))
            <a class="tab" href="{{ route('admin.master.projects.budget.show', $project) }}" title="{{ __('Detailed budget / BOQ, versions and Budget vs Actual') }}">{{ __('Budget / BOQ') }}</a>
        @endif
        @foreach($panels as $key => $definition)
            <a class="tab" href="{{ route('admin.master.projects.workspace.panel', [$project, $key]).'#'.$key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.master.projects.workspace.panel', [$project, $key]) }}">{{ __($definition->title) }}</a>
        @endforeach
    </nav>
    <div data-workspace-form>
        @if($manage)
            @include('admin.master.projects._form')
        @else
            <x-admin.data-table :title="__('Project Overview (read-only)')">
                <tbody>
                    <tr><th>{{ __('Location') }}</th><td>{{ $project->location ?: __('Not set') }}</td></tr>
                    <tr><th>{{ __('Description') }}</th><td>{{ $project->description ?: __('No description yet.') }}</td></tr>
                    <tr><th>{{ __('Workflow') }}</th><td>{{ __('Related records save independently. Posting, receiving and payment remain explicit actions in their business screens. No project progress percentage is calculated.') }}</td></tr>
                </tbody>
            </x-admin.data-table>
        @endif
    </div>
    <x-admin.workspace-host :close-url="route('admin.master.projects.index')"/>
</div>
