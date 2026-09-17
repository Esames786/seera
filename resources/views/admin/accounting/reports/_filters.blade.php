{{-- Shared report filters: quick date presets that resolve server-side (NR-21) and CSV export of the same filtered values (NR-20). --}}
<x-admin.filter-bar>
    <select class="select" style="width:170px" name="preset" data-report-preset title="Quick date range">
        @foreach ($presets as $key => $label)
            <option value="{{ $key }}" @selected($period->preset === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <input class="input" style="width:150px" type="date" name="from" value="{{ $period->from?->toDateString() }}" title="From"/>
    <input class="input" style="width:150px" type="date" name="to" value="{{ $period->to?->toDateString() }}" title="To"/>
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
            <option value="">All Locations</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}" @selected(request('site') == $site->id)>{{ $site->name }}</option>
            @endforeach
        </select>
    @endif
    @if (($showVatStatus ?? false))
        <select class="select" style="width:150px" name="status">
            <option value="">All Status</option>
            @foreach ($vatStatuses as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    @endif
    <x-slot:actions>
        <a class="btn outline" href="{{ url()->current() }}">Reset</a>
        <button type="submit" name="export" value="csv" class="btn outline" title="Download the report as it is filtered here, for Excel">Export Excel (CSV)</button>
        <button type="button" class="btn outline" onclick="window.print()">Export PDF</button>
    </x-slot:actions>
</x-admin.filter-bar>

<div class="small" style="margin:-6px 0 14px 2px">
    Showing: <strong>{{ $period->label() }}</strong>
    @if ($period->preset !== 'custom')
        · pick <em>Custom Range</em> or edit the dates for another period
    @endif
</div>

<script>
    (function () {
        document.querySelectorAll('[data-report-preset]').forEach(function (select) {
            var form = select.form;
            if (!form) return;
            var dates = form.querySelectorAll('input[type="date"]');

            // Choosing a preset applies it immediately; typing a date switches to a custom range.
            select.addEventListener('change', function () {
                if (select.value !== 'custom') {
                    dates.forEach(function (input) { input.value = ''; });
                    form.submit();
                }
            });
            dates.forEach(function (input) {
                input.addEventListener('change', function () { select.value = 'custom'; });
            });
        });
    })();
</script>
