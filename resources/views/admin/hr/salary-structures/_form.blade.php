@php
    /** @var \App\Models\SalaryStructure|null $structure */
    $structure = $structure ?? null;
    $items = old('items', $structure?->items->map(fn ($item) => [
        'item_type' => $item->item_type,
        'name' => $item->name,
        'amount' => $item->amount,
        'is_taxable' => $item->is_taxable,
    ])->all() ?? []);
    $rows = max(count($items), 3);
@endphp

<form method="POST" action="{{ $structure ? route('admin.hr.salary-structures.update', $structure) : route('admin.hr.salary-structures.store') }}">
    @csrf
    @if ($structure) @method('PUT') @endif

    <x-admin.form-section title="Salary Structure" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $structure?->employee_id) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="effective_from">Effective From *</label><input id="effective_from" name="effective_from" type="date" class="input" value="{{ old('effective_from', $structure?->effective_from?->toDateString() ?? now()->startOfMonth()->toDateString()) }}" required/></div>
        <div><label for="effective_to">Effective To</label><input id="effective_to" name="effective_to" type="date" class="input" value="{{ old('effective_to', $structure?->effective_to?->toDateString()) }}"/></div>
        <div><label for="basic_salary">Basic Salary *</label><input id="basic_salary" name="basic_salary" type="number" step="0.01" min="0" class="input" value="{{ old('basic_salary', $structure?->basic_salary ?? 0) }}" required/></div>
        <div><label for="housing_allowance">Housing Allowance *</label><input id="housing_allowance" name="housing_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('housing_allowance', $structure?->housing_allowance ?? 0) }}" required/></div>
        <div><label for="transport_allowance">Transport Allowance *</label><input id="transport_allowance" name="transport_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('transport_allowance', $structure?->transport_allowance ?? 0) }}" required/></div>
        <div><label for="food_allowance">Food Allowance *</label><input id="food_allowance" name="food_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('food_allowance', $structure?->food_allowance ?? 0) }}" required/></div>
        <div><label for="other_allowance">Other Allowance *</label><input id="other_allowance" name="other_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('other_allowance', $structure?->other_allowance ?? 0) }}" required/></div>
        <div><label for="fixed_deduction">Fixed Deduction *</label><input id="fixed_deduction" name="fixed_deduction" type="number" step="0.01" min="0" class="input" value="{{ old('fixed_deduction', $structure?->fixed_deduction ?? 0) }}" required/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $structure?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="Additional Salary Items">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th style="width:160px">Type</th><th>Name</th><th style="width:160px">Amount</th><th style="width:120px">Taxable</th></tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < $rows; $i++)
                        @php $item = $items[$i] ?? []; @endphp
                        <tr>
                            <td>
                                <select name="items[{{ $i }}][item_type]" class="select">
                                    @foreach (['allowance', 'deduction'] as $type)
                                        <option value="{{ $type }}" @selected(($item['item_type'] ?? 'allowance') === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="items[{{ $i }}][name]" class="input" value="{{ $item['name'] ?? '' }}" placeholder="e.g. Site Allowance"/></td>
                            <td><input name="items[{{ $i }}][amount]" type="number" step="0.01" min="0" class="input" value="{{ $item['amount'] ?? '' }}"/></td>
                            <td>
                                <select name="items[{{ $i }}][is_taxable]" class="select">
                                    <option value="0" @selected(! ($item['is_taxable'] ?? false))>No</option>
                                    <option value="1" @selected($item['is_taxable'] ?? false)>Yes</option>
                                </select>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">Rows left without a name are ignored.</div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.salary-structures.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $structure ? 'Update Salary Structure' : 'Save Salary Structure' }}</button>
    </div>
</form>
