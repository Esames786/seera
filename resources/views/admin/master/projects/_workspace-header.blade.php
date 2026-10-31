<section class="table-card" aria-label="Project identity" data-project-identity="{{ $project->id }}">
    <div class="table-title"><strong>{{ $project->code }} / {{ $project->name }}</strong><x-admin.status-badge :status="$project->status"/></div>
    <div style="padding:16px;display:flex;flex-wrap:wrap;gap:16px">
        <span>{{ __('Customer') }}: {{ $project->customer?->name ?? __('Not assigned') }}</span>
        <span>{{ __('Manager') }}: {{ $project->manager?->name ?? __('Not assigned') }}</span>
        <span>{{ __('Classification') }}: {{ $project->classification?->name ?? __('Not set') }}</span>
        <span>{{ __('Branch') }}: {{ $project->branch?->name ?? __('Not assigned') }}</span>
        <span>{{ __('Start') }}: {{ $project->start_date?->toDateString() ?? __('Not set') }}</span>
        <span>{{ __('End') }}: {{ $project->end_date?->toDateString() ?? __('Not set') }}</span>
        <span>{{ __('Master budget') }}: SAR {{ number_format((float) $project->budget, 2) }}</span>
        <span>{{ __('Detailed current budget') }}: {{ $project->currentBudget ? 'SAR '.number_format((float) $project->currentBudget->total_amount, 2).' ('.$project->currentBudget->reference.')' : __('none yet') }}</span>
    </div>
    <div style="padding:0 16px 16px;display:flex;flex-wrap:wrap;gap:16px">
        @foreach($summary as $label => $value)<span><strong>{{ __($label) }}:</strong> {{ $value }}</span>@endforeach
    </div>
    @if(auth()->user()->hasPermission('Projects', 'view'))
        <div style="padding:0 16px 16px;display:flex;flex-wrap:wrap;gap:8px">
            <a class="btn sm outline" href="{{ route('admin.reports.projects.index', ['search' => $project->code]) }}">{{ __('Open Project Reports') }}</a>
            <a class="btn sm outline" href="{{ route('admin.master.projects.budget.show', $project) }}">{{ __('Budget / BOQ') }}</a>
        </div>
    @endif
</section>
