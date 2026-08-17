@extends('layouts.admin')

@section('title', 'Financial Reports')
@section('breadcrumb', 'Accounting / Financial Reports')

@section('content')
    <x-admin.page-header title="Financial Reports" description="Balance sheet, profit &amp; loss, trial balance, cash flow, VAT and project cost, all built from posted journal entries"/>

    <div class="card-grid">
        @foreach ([
            ['reports.balance-sheet', 'Balance Sheet', 'Assets, liabilities and equity at a point in time', 'blue'],
            ['reports.profit-loss', 'Profit & Loss', 'Revenue less expenses for the selected period', 'green'],
            ['reports.trial-balance', 'Trial Balance', 'Debit and credit balance per account', 'cyan'],
            ['reports.cash-flow', 'Cash Flow', 'Opening cash, cash in, cash out, closing cash', 'yellow'],
        ] as [$route, $title, $description, $color])
            <div class="card metric {{ $color }}">
                <div class="value" style="font-size:18px">{{ $title }}</div>
                <div class="label">{{ $description }}</div>
                <br/>
                <a class="btn sm primary" href="{{ route('admin.accounting.'.$route) }}">Open Report</a>
            </div>
        @endforeach
    </div>

    <div class="card-grid">
        @foreach ([
            ['reports.vat-report', 'VAT Report', 'Output VAT, input VAT and VAT payable per period', 'blue'],
            ['reports.project-cost-report', 'Project Cost Report', 'Budget, cost, revenue and margin per project', 'green'],
        ] as [$route, $title, $description, $color])
            <div class="card metric {{ $color }}">
                <div class="value" style="font-size:18px">{{ $title }}</div>
                <div class="label">{{ $description }}</div>
                <br/>
                <a class="btn sm primary" href="{{ route('admin.accounting.'.$route) }}">Open Report</a>
            </div>
        @endforeach
    </div>

    <div class="help-box">
        Every report reads posted journal entries only. Draft and cancelled journals are excluded, so the reports always match the general ledger.
    </div>
@endsection
