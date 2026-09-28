@include('admin.master.projects._workspace-header')
<div data-workspace>
    <nav class="tabs workspace-nav" data-workspace-nav aria-label="Project sections">
        <a class="tab" href="#profile" data-workspace-section="profile">Overview</a>
        @foreach($panels as $key => $definition)
            <a class="tab" href="{{ route('admin.master.projects.workspace.panel', [$project, $key]).'#'.$key }}" data-workspace-related="{{ $key }}" data-related-url="{{ route('admin.master.projects.workspace.panel', [$project, $key]) }}">{{ __($definition->title) }}</a>
        @endforeach
    </nav>
    <div data-workspace-form>
        @if($manage)
            @include('admin.master.projects._form')
        @else
            <x-admin.data-table title="Project Overview (read-only)">
                <tbody>
                    <tr><th>Location</th><td>{{ $project->location ?: 'Not set' }}</td></tr>
                    <tr><th>Description</th><td>{{ $project->description ?: 'No description yet.' }}</td></tr>
                    <tr><th>Phase A</th><td>Related records save independently. Posting, receiving and payment remain explicit actions in their business screens. No project progress percentage is calculated.</td></tr>
                </tbody>
            </x-admin.data-table>
        @endif
    </div>
    <x-admin.workspace-host :close-url="route('admin.master.projects.index')"/>
</div>
