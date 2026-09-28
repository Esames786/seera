<section class="table-card" aria-label="Project identity" data-project-identity="{{ $project->id }}">
    <div class="table-title"><strong>{{ $project->code }} / {{ $project->name }}</strong><x-admin.status-badge :status="$project->status"/></div>
    <div style="padding:16px;display:flex;flex-wrap:wrap;gap:16px">
        <span>Customer: {{ $project->customer?->name ?? 'Not assigned' }}</span>
        <span>Manager: {{ $project->manager?->name ?? 'Not assigned' }}</span>
        <span>Classification: {{ $project->classification?->name ?? 'Not set' }}</span>
        <span>Branch: {{ $project->branch?->name ?? 'Not assigned' }}</span>
        <span>Start: {{ $project->start_date?->toDateString() ?? 'Not set' }}</span>
        <span>End: {{ $project->end_date?->toDateString() ?? 'Not set' }}</span>
        <span>Master budget: SAR {{ number_format((float) $project->budget, 2) }}</span>
    </div>
    <div style="padding:0 16px 16px;display:flex;flex-wrap:wrap;gap:16px">
        @foreach($summary as $label => $value)<span><strong>{{ $label }}:</strong> {{ $value }}</span>@endforeach
    </div>
</section>
