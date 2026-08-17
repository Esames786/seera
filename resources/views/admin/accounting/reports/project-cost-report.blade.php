@extends('layouts.admin')

@section('title', 'Project Cost Report')
@section('breadcrumb', 'Accounting / Financial Reports / Project Cost Report')

@section('content')
    <x-admin.page-header title="Project Cost Report" description="Budget, posted cost, revenue and margin per project">
        <a class="btn outline" href="{{ route('admin.accounting.reports.index') }}">All Reports</a>
    </x-admin.page-header>

    @include('admin.accounting.reports._filters', ['showScope' => false])

    <div class="card-grid">
        <x-admin.metric-card color="red" :value="'SAR '.number_format($totalCost, 2)" label="Total Posted Cost"/>
        <x-admin.metric-card color="green" :value="'SAR '.number_format($totalRevenue, 2)" label="Total Posted Revenue"/>
        <x-admin.metric-card :color="$totalRevenue - $totalCost >= 0 ? 'green' : 'red'" :value="'SAR '.number_format($totalRevenue - $totalCost, 2)" label="Overall Margin"/>
        <x-admin.metric-card color="blue" :value="$rows->count()" label="Projects"/>
    </div>

    <x-admin.data-table title="Project Cost &amp; Revenue">
        <thead>
            <tr><th>Project</th><th>Client</th><th>Budget</th><th>Posted Cost</th><th>Budget Used</th><th>Posted Revenue</th><th>Supplier Billed</th><th>Customer Invoiced</th><th>Margin</th></tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('admin.master.projects.show', $row['project']) }}" style="color:var(--blue);font-weight:700">{{ $row['project']->name }}</a></td>
                    <td>{{ $row['project']->customer?->name ?? '-' }}</td>
                    <td>{{ number_format($row['budget'], 2) }}</td>
                    <td>{{ number_format($row['cost'], 2) }}</td>
                    <td>
                        {{ number_format($row['budget_used'], 1) }}%
                        @if ($row['budget_used'] > 90)
                            <span class="badge red">High</span>
                        @endif
                    </td>
                    <td>{{ number_format($row['revenue'], 2) }}</td>
                    <td>{{ number_format($row['billed'], 2) }}</td>
                    <td>{{ number_format($row['invoiced'], 2) }}</td>
                    <td><strong>{{ number_format($row['margin'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="9" class="table-empty">No projects to report on.</td></tr>
            @endforelse
        </tbody>
    </x-admin.data-table>

    <div class="note">
        Posted cost and revenue come from journal lines tagged with the project. Supplier billed and customer invoiced are document totals, including drafts that are already approved.
    </div>
@endsection
