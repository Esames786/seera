<p>{{ __('supplier_advances.help') }}</p>
@if ($canPostAdvance)
    <details>
        <summary>{{ __('supplier_advances.record') }}</summary>
        <form method="POST" action="{{ route('admin.accounting.accounts-payable.advances.store', $supplier) }}" data-related-save>
            @csrf
            <div class="alert" role="alert" data-related-errors hidden></div>
            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <div class="form-grid three">
                <div><label>{{ __('supplier_advances.purpose') }}</label><select class="select" name="purpose" required>@foreach (\App\Models\SupplierAdvance::PURPOSES as $purpose)<option value="{{ $purpose }}">{{ __('supplier_advances.'.$purpose) }}</option>@endforeach</select></div>
                <div><label>{{ __('supplier_advances.date') }}</label><input class="input" type="date" name="payment_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required></div>
                <div><label>{{ __('supplier_advances.amount') }}</label><input class="input" name="amount" type="number" min="0.01" step="0.01" required></div>
                <div><label>{{ __('supplier_advances.project') }}</label><select class="select" name="project_id"><option value="">—</option>@foreach ($advanceProjects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select></div>
                <div><label>{{ __('supplier_advances.site') }}</label><select class="select" name="site_id"><option value="">—</option>@foreach ($advanceSites as $site)<option value="{{ $site->id }}">{{ $site->name }}</option>@endforeach</select></div>
                <div><label>{{ __('supplier_advances.payment_account') }}</label><select class="select" name="payment_account_id" required><option value="">—</option>@foreach ($advanceAccounts as $account)<option value="{{ $account->id }}">{{ $account->label() }}</option>@endforeach</select></div>
                <div><label>{{ __('supplier_advances.reference') }}</label><input class="input" name="reference" maxlength="100" required></div>
                <div><label>{{ __('supplier_advances.reason') }}</label><textarea class="input" name="reason" minlength="5" maxlength="2000" required></textarea></div>
            </div>
            <p><label><input type="checkbox" name="recoverable_confirmed" value="1" required> {{ __('supplier_advances.confirm') }}</label></p>
            <p>{{ __('supplier_advances.mapping_help') }}</p>
            <button class="btn primary" type="submit">{{ __('supplier_advances.post') }}</button>
        </form>
    </details>
@endif
@forelse ($rows as $advance)
    <section class="card" style="padding:16px;margin-block:16px;min-width:0">
        <h3>#{{ $advance->id }} · {{ __('supplier_advances.'.$advance->purpose) }}</h3>
        <p>{{ $advance->payment_date->format('Y-m-d') }} · {{ $advance->reference }} · {{ __('supplier_advances.amount') }}: {{ $money($advance->amount) }} · {{ __('supplier_advances.available') }}: {{ $money($advance->available()) }}</p>
        <p style="overflow-wrap:anywhere">{{ $advance->reason }}</p>
        @if ($advance->reversed_at)<p>{{ __('supplier_advances.reversed') }}: {{ $advance->reversal_reason }}</p>@endif
        @if ($canPostAdvance && ! $advance->reversed_at && $advance->available() > 0)
            @foreach (['apply', 'refund'] as $kind)
                <details><summary>{{ __('supplier_advances.'.$kind) }}</summary>
                    <form method="POST" action="{{ route('admin.accounting.accounts-payable.advances.settle', [$supplier, $advance, $kind]) }}" data-related-save>
                        @csrf
                        <div class="alert" data-related-errors role="alert" hidden></div>
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <div class="form-grid three">
                            @if ($kind === 'apply')
                                <div><label>{{ __('supplier_advances.bill') }}</label><select class="select" name="supplier_bill_id" required><option value="">—</option>@foreach ($advanceBills->where('project_id', $advance->project_id)->where('site_id', $advance->site_id) as $bill)<option value="{{ $bill->id }}">{{ $bill->bill_number }} · {{ $money($bill->balance_amount) }}</option>@endforeach</select></div>
                            @else
                                <div><label>{{ __('supplier_advances.payment_account') }}</label><select class="select" name="payment_account_id" required><option value="">—</option>@foreach ($advanceAccounts as $account)<option value="{{ $account->id }}">{{ $account->label() }}</option>@endforeach</select></div>
                            @endif
                            <div><label>{{ __('supplier_advances.date') }}</label><input class="input" type="date" name="movement_date" value="{{ now()->toDateString() }}" required></div>
                            <div><label>{{ __('supplier_advances.amount') }}</label><input class="input" type="number" name="amount" step="0.01" min="0.01" max="{{ $advance->available() }}" required></div>
                            <div><label>{{ __('supplier_advances.reference') }}</label><input class="input" name="reference" maxlength="100" required></div>
                        </div>
                        <button class="btn primary" type="submit">{{ __('supplier_advances.'.$kind) }}</button>
                    </form>
                </details>
            @endforeach
        @endif
        <div class="table-wrap"><table>
            <thead><tr><th>{{ __('supplier_advances.date') }}</th><th>{{ __('supplier_advances.action') }}</th><th>{{ __('supplier_advances.reference') }}</th><th>{{ __('supplier_advances.amount') }}</th><th>{{ __('supplier_advances.action') }}</th></tr></thead>
            <tbody>@foreach ($advance->movements as $movement)<tr>
                <td>{{ $movement->movement_date->format('Y-m-d') }}</td><td>{{ __('supplier_advances.'.$movement->kind) }} {{ $movement->bill?->bill_number }}</td><td>{{ $movement->reference }}</td><td>{{ $money($movement->amount) }}</td>
                <td>@if ($movement->reversed_at){{ __('supplier_advances.reversed') }}@elseif ($canPostAdvance)
                    <form method="POST" action="{{ route('admin.accounting.accounts-payable.advances.movements.reverse', [$supplier, $advance, $movement]) }}" data-related-save>@csrf<div class="alert" data-related-errors hidden></div><label>{{ __('supplier_advances.reason') }}<input class="input" name="reason" minlength="5" maxlength="500" required></label><button class="btn outline" type="submit">{{ __('supplier_advances.reverse') }}</button></form>
                @endif</td>
            </tr>@endforeach</tbody>
        </table></div>
        @if ($canPostAdvance && ! $advance->reversed_at && ! $advance->movements->whereNull('reversed_at')->count())
            <form method="POST" action="{{ route('admin.accounting.accounts-payable.advances.reverse', [$supplier, $advance]) }}" data-related-save>@csrf<div class="alert" data-related-errors hidden></div><label>{{ __('supplier_advances.reason') }}<input class="input" name="reason" minlength="5" maxlength="500" required></label><button class="btn outline" type="submit">{{ __('supplier_advances.reverse') }}</button></form>
        @endif
    </section>
@empty
    <p>{{ __('supplier_advances.empty') }}</p>
@endforelse
