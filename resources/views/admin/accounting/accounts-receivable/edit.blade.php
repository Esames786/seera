@extends('layouts.admin')

@section('title', 'Edit Customer Invoice')
@section('breadcrumb', 'Accounting / Accounts Receivable / Edit Customer Invoice')

@section('content')
    <x-admin.page-header :title="'Edit Invoice: '.$invoice->invoice_number" description="Only a draft invoice can be edited">
        <a class="btn outline" href="{{ route('admin.accounting.accounts-receivable.show', ['accounts_receivable' => $invoice, 'return_to' => $returnTo ?? null]) }}">View Details</a>
    </x-admin.page-header>

    @include('admin.accounting.accounts-receivable._form', ['invoice' => $invoice])
@endsection
