@php
    $prefix = 'employee-'.$employee->id.'-'.$panel.'-';
    $panelUrl = route('admin.hr.employees.workspace.panel', [$employee, $panel]);
@endphp
<div class="card" style="padding:18px" data-panel-content>
    <h2>{{ __($title) }} — {{ $employee->employee_code }} / {{ $employee->name }}</h2>
    <p class="small">{{ __('Employee is fixed to this workspace. Each section saves independently.') }}</p>
    <div role="status" aria-live="polite" data-panel-status></div>
    @if($panel === 'salary')
        <p class="help-box">{{ __('New effective-dated structure only. Existing salary structures and past payroll are not overwritten.') }}</p>
    @elseif($panel === 'eosb')
        <p class="help-box">{{ __('Review the final wage and service years. Saving creates a draft; approval is a separate action.') }}</p>
    @elseif($panel === 'account')
        <p class="help-box">{{ __('Identity and employment details come from this employee. Choose role and security settings explicitly.') }}</p>
    @endif
    @if($canSave)
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.hr.employees.workspace.save', [$employee, $panel]) }}" data-related-save>
            @csrf
            <input type="hidden" name="employee_id" value="{{ $employee->id }}"/>
            @if($record)<input type="hidden" name="record_id" value="{{ $record->id }}"/>@endif
            <div class="alert" data-related-errors role="alert" hidden></div>
            <div class="form-grid three">
                @foreach($fields as $field)
                    <div class="{{ in_array($field['type'], ['textarea', 'file']) ? 'full' : '' }}">
                        <label for="{{ $prefix.$field['name'] }}">{{ __($field['label']) }}{{ $field['required'] ? ' *' : '' }}</label>
                        @if($field['type'] === 'select')
                            <select id="{{ $prefix.$field['name'] }}" name="{{ $field['name'] }}" class="select" @required($field['required'])>
                                <option value="">{{ __('Select...') }}</option>
                                @foreach($field['options'] as $value => $label)
                                    <option value="{{ $value }}" @selected((string)$field['value'] === (string)$value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        @elseif($field['type'] === 'textarea')
                            <textarea id="{{ $prefix.$field['name'] }}" name="{{ $field['name'] }}" class="textarea">{{ $field['value'] }}</textarea>
                        @elseif($field['type'] === 'file')
                            <input id="{{ $prefix.$field['name'] }}" name="{{ $field['name'] }}" type="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp"/>
                            <small>{{ __('PDF or image, up to 5 MB. Existing attachment stays unless replaced.') }}</small>
                        @else
                            <input id="{{ $prefix.$field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" class="input" value="{{ $field['value'] }}" @required($field['required']) @if($field['type'] === 'number') min="0" step="any" @endif @if($field['type'] === 'password') autocomplete="new-password" minlength="8" @endif/>
                        @endif
                        <div class="field-error" data-field-error="{{ $field['name'] }}"></div>
                    </div>
                @endforeach
            </div>
            @if($panel === 'salary')
                <h3>{{ __('Additional allowances / deductions') }}</h3>
                <div data-salary-items></div>
                <button type="button" class="btn outline" data-add-salary-item>{{ __('Add item') }}</button>
                <template data-salary-item-template>
                    <div class="form-grid three" data-salary-item>
                        <label>{{ __('Type') }}<select name="items[__INDEX__][item_type]" class="select"><option value="allowance">{{ __('Allowance') }}</option><option value="deduction">{{ __('Deduction') }}</option></select></label>
                        <label>{{ __('Name') }}<input name="items[__INDEX__][name]" class="input" maxlength="100" required/></label>
                        <label>{{ __('Amount') }}<input name="items[__INDEX__][amount]" type="number" min="0" step="0.01" class="input" value="0" required/></label>
                        <label>{{ __('Taxable') }}<select name="items[__INDEX__][is_taxable]" class="select"><option value="0">{{ __('No') }}</option><option value="1">{{ __('Yes') }}</option></select></label>
                        <button type="button" class="btn outline" data-remove-salary-item>{{ __('Remove') }}</button>
                    </div>
                </template>
            @endif
            <div class="form-actions">
                <button type="submit" class="btn primary">{{ $record ? __('Save changes here') : __('Save here') }}</button>
                @if($record && $panel !== 'account')<button type="button" class="btn outline" data-panel-load="{{ $panelUrl }}">{{ __('Cancel editing / new entry') }}</button>@endif
            </div>
        </form>
    @elseif($panel !== 'payroll-history')
        <p>{{ __('This section is read-only for this record or your permissions.') }}</p>
        @if($canCreate && $panel !== 'account')<button class="btn outline" type="button" data-panel-load="{{ $panelUrl }}">{{ __('New entry') }}</button>@endif
    @endif
    <h3>{{ __('Saved records') }}</h3>
    <div class="table-wrap"><table>
        <thead><tr>@foreach($columns as $column)<th>{{ __(\Illuminate\Support\Str::headline($column)) }}</th>@endforeach<th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($columns as $column)
                        @php $value = $row->{$column}; @endphp
                        <td>{{ $value instanceof \Carbon\CarbonInterface ? $value->toDateString() : ($value ?? '-') }}</td>
                    @endforeach
                    <td>
                        @if(\App\Support\EmployeeWorkspacePanels::editable($panel, $row))
                            @if(auth()->user()->hasPermission($module, 'edit'))<button type="button" class="btn sm outline" data-panel-load="{{ $panelUrl.'?record='.$row->id }}">{{ __('Edit here') }}</button>@endif
                            @foreach(['approve', 'reject', 'delete'] as $action)
                                @php
                                    $permittedAction = $action === 'delete'
                                        ? in_array($panel, ['attendance', 'leaves', 'overtime', 'shifts', 'eosb'])
                                        : (in_array($panel, ['leaves', 'overtime', 'eosb']) && ($action !== 'reject' || $panel === 'leaves'));
                                @endphp
                                @if($permittedAction && auth()->user()->hasPermission($module, $action))
                                    <button type="button" class="btn sm outline" data-related-action="{{ route('admin.hr.employees.workspace.action', [$employee, $panel, $row->id, $action]) }}" data-action="{{ $action }}">{{ __(ucfirst($action)) }}</button>
                                @endif
                            @endforeach
                        @endif
                        @if($panel === 'leaves' && $row->attachment_path)
                            <a class="btn sm outline" href="{{ route('admin.hr.leaves.attachment', $row) }}" target="_blank" rel="noopener">{{ __('Attachment') }}</a>
                        @endif
                    </td>
                </tr>
                @if($panel === 'salary')
                    <tr><td colspan="{{ count($columns)+1 }}">{{ __('Allowances') }}: {{ $row->totalAllowances() }} · {{ __('Deductions') }}: {{ $row->totalDeductions() }} · {{ __('Net') }}: {{ $row->netSalary() }}</td></tr>
                @elseif($panel === 'payroll-history')
                    <tr><td colspan="{{ count($columns)+1 }}">{{ $row->payrollRun?->code }} · {{ $row->payrollRun?->periodLabel() }} · {{ $row->payrollRun?->status }} · {{ __('Present days') }}: {{ $row->present_days }} · {{ __('Leave days') }}: {{ $row->leave_days }}</td></tr>
                @endif
            @empty
                <tr><td colspan="{{ count($columns)+1 }}">{{ __('No records yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div class="form-actions">
        @if($rows->currentPage() > 1)<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage()-1) }}">{{ __('Previous') }}</button>@endif
        <span>{{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>
        @if($rows->hasMorePages())<button type="button" class="btn outline" data-panel-load="{{ $panelUrl.'?page='.($rows->currentPage()+1) }}">{{ __('Next') }}</button>@endif
    </div>
</div>
