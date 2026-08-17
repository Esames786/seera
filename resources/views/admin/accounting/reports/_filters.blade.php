<x-admin.filter-bar>
    <input class="input" style="width:150px" type="date" name="from" value="{{ request('from') }}"/>
    <input class="input" style="width:150px" type="date" name="to" value="{{ request('to') }}"/>
    @if (($showScope ?? true))
        <select class="select" style="width:160px" name="cost_center">
            <option value="">All Cost Centers</option>
            @foreach ($costCenters as $costCenter)
                <option value="{{ $costCenter->id }}" @selected(request('cost_center') == $costCenter->id)>{{ $costCenter->code }}</option>
            @endforeach
        </select>
        <select class="select" style="width:150px" name="project">
            <option value="">All Projects</option>
            @foreach ($projects as $project)
                <option value="{{ $project->id }}" @selected(request('project') == $project->id)>{{ $project->name }}</option>
            @endforeach
        </select>
        <select class="select" style="width:140px" name="site">
            <option value="">All Sites</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
    @endif
    <x-slot:actions>
        <a class="btn outline" href="{{ url()->current() }}">Reset</a>
        <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
    </x-slot:actions>
</x-admin.filter-bar>
