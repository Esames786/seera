@extends('layouts.admin')

@section('title', 'Chart of Accounts')
@section('breadcrumb', 'Accounting / Chart of Accounts')

@section('content')
    <x-admin.page-header title="Chart of Accounts" description="Assets, liabilities, equity, revenue and expense accounts used by every posting">
        <a class="btn primary" href="{{ route('admin.accounting.chart-of-accounts.create') }}">+ Add Account</a>
    </x-admin.page-header>

    <div class="card-grid">
        <x-admin.metric-card color="blue" :value="$totalAccounts" label="Total Accounts"/>
        <x-admin.metric-card color="green" :value="$activeAccounts" label="Active Accounts"/>
        <x-admin.metric-card color="yellow" :value="$vatAccounts" label="VAT Applicable"/>
        <x-admin.metric-card color="cyan" :value="$costCenterAccounts" label="Cost Center Required"/>
    </div>

    <div class="split even">
        <div class="tree">
            <div class="table-title" style="border:0;padding:0 0 12px">Account Tree</div>
            <ul>
                @forelse ($rootAccounts as $root)
                    @include('admin.accounting.chart-of-accounts._tree-node', ['node' => $root])
                @empty
                    <li class="small">No accounts yet.</li>
                @endforelse
            </ul>
        </div>

        <div>
            <x-admin.filter-bar>
                <input class="input" style="width:200px" type="search" name="search" value="{{ request('search') }}" placeholder="Code or account name..."/>
                <select class="select" style="width:150px" name="type">
                    <option value="">All Types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
                <select class="select" style="width:130px" name="status">
                    <option value="">All Status</option>
                    @foreach (['active', 'inactive'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <x-slot:actions>
                    <a class="btn outline" href="{{ route('admin.accounting.chart-of-accounts.index') }}">Reset</a>
                </x-slot:actions>
            </x-admin.filter-bar>

            <x-admin.data-table title="Accounts Listing" :subtitle="$accounts->count().' accounts'">
                <thead>
                    <tr><th>Code</th><th>Account</th><th>Type</th><th>Parent</th><th>Normal</th><th>Opening</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>{{ $account->account_code }}</td>
                            <td><a href="{{ route('admin.accounting.chart-of-accounts.show', $account) }}" style="color:var(--blue);font-weight:700">{{ $account->account_name }}</a></td>
                            <td>{{ ucfirst($account->account_type) }}</td>
                            <td>{{ $account->parent?->account_code ?? '-' }}</td>
                            <td>{{ ucfirst($account->normal_balance) }}</td>
                            <td>SAR {{ number_format($account->opening_balance, 2) }}</td>
                            <td><x-admin.status-badge :status="$account->status"/></td>
                            <td>
                                <x-admin.action-buttons
                                    :view="route('admin.accounting.chart-of-accounts.show', $account)"
                                    :edit="route('admin.accounting.chart-of-accounts.edit', $account)"
                                    :delete="route('admin.accounting.chart-of-accounts.destroy', $account)"
                                    :name="$account->label()"/>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="table-empty">No accounts match the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.data-table>
        </div>
    </div>

    <div class="help-box">
        An account that already carries journal lines or sub-accounts is deactivated instead of deleted, so historical postings stay intact.
    </div>
@endsection
