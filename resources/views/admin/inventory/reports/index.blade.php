@extends('layouts.admin')

@section('title', 'Inventory Reports')
@section('breadcrumb', 'Inventory / Inventory Reports')

@section('content')
    <x-admin.page-header title="Inventory Reports" description="Stock valuation, low stock, project material consumption and stock movement"/>

    <div class="card-grid">
        @foreach ([
            ['reports.stock-valuation', 'Stock Valuation', 'Quantity, average cost and value per item and warehouse', 'blue'],
            ['reports.low-stock', 'Low Stock', 'Items at or below their reorder level', 'red'],
            ['reports.project-consumption', 'Project Consumption', 'Material issued to each project, from the stock ledger', 'green'],
            ['reports.movement', 'Stock Movement', 'Total in, total out and movement value per item', 'cyan'],
        ] as [$route, $title, $description, $color])
            <div class="card metric {{ $color }}">
                <div class="value" style="font-size:18px">{{ $title }}</div>
                <div class="label">{{ $description }}</div>
                <br/>
                <a class="btn sm primary" href="{{ route('admin.inventory.'.$route) }}">Open Report</a>
            </div>
        @endforeach
    </div>

    <div class="help-box">
        Stock valuation and low stock read the live warehouse stock table. Consumption and movement read the stock ledger, so they only reflect posted documents.
    </div>
@endsection
