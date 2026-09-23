@php
    /** @var \App\Models\Employee|null $employee */
    $employee = $employee ?? null;
    $documentRows = max(count(old('documents', [])), 4);
@endphp

<nav class="tabs employee-workspace-nav" aria-label="{{ __('ui.employee_sections') }}" hidden>
    @foreach (['personal', 'employment', 'payroll', 'documents', 'access'] as $section)
        <a class="tab" href="#{{ $section }}" data-employee-section="{{ $section }}">{{ __('ui.section_'.$section) }}</a>
    @endforeach
    <button type="button" class="btn sm outline" data-employee-all>{{ __('ui.show_all_sections') }}</button>
</nav>
<form method="POST" action="{{ $employee ? route("admin.hr.employees.update", $employee) : route("admin.hr.employees.store") }}" enctype="multipart/form-data" data-employee-workspace="{{ $employee ? 'edit' : 'create' }}">
    @csrf
    @if ($employee) @method('PUT') @endif
    <input type="hidden" name="_workspace_section" value="{{ old('_workspace_section', 'personal') }}" data-dirty-ignore/>

    <x-admin.form-section title="A. Personal Information" columns="3">
        <div><label for="first_name">First Name *</label><input id="first_name" name="first_name" class="input" value="{{ old('first_name', $employee?->first_name) }}" required/></div>
        <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" class="input" value="{{ old('last_name', $employee?->last_name) }}"/></div>
        <div>
            <div class="label-row">
                <label for="nationality">Nationality</label>
                <x-admin.quick-create id="qc-nationality" target="nationality" :url="route('admin.master.lookup-values.store')" title="New Nationality" permission="HR" submit="Add Nationality">
                    <div class="full"><label for="qc-nat-value">Nationality *</label><input id="qc-nat-value" name="value" class="input" placeholder="e.g. Jordanian" required/></div>
                    <input type="hidden" name="type" value="nationality"/>
                </x-admin.quick-create>
            </div>
            <select id="nationality" name="nationality" class="select">
                <option value="">Select...</option>
                @foreach ($nationalities as $nationality)
                    <option value="{{ $nationality }}" @selected(old('nationality', $employee?->nationality) === $nationality)>{{ $nationality }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="email">Email</label><input id="email" name="email" type="email" class="input" value="{{ old('email', $employee?->email) }}" placeholder="employee@company.sa"/></div>
        <div><label for="phone">Phone</label><input id="phone" name="phone" class="input" value="{{ old('phone', $employee?->phone) }}" placeholder="+966..."/></div>
        <div><label for="emergency_contact">Emergency Contact</label><input id="emergency_contact" name="emergency_contact" class="input" value="{{ old('emergency_contact', $employee?->emergency_contact) }}" placeholder="+966..."/></div>
    </x-admin.form-section>

    <x-admin.form-section title="B. Employment Information" columns="3">
        <div>
            <label for="employee_code">Employee Code {{ $employee ? '*' : '' }}</label>
            @if ($employee)
                <input id="employee_code" name="employee_code" class="input" value="{{ old('employee_code', $employee->employee_code) }}" required/>
            @else
                {{-- The next number is shown straight away (FR-06). The real field stays --}}
                {{-- empty so the server assigns the number at save and two people --}}
                {{-- filling the form at the same time cannot take the same code. --}}
                <input id="employee_code_preview" class="input" value="{{ $nextCodes[old('employee_classification', 'Sponsorship')] ?? reset($nextCodes) }}" readonly aria-label="Employee code assigned automatically"/>
                <input id="employee_code" name="employee_code" class="input" value="{{ old('employee_code') }}" placeholder="Your own code" style="margin-top:6px" hidden/>
                <label class="small" style="display:flex;align-items:center;gap:6px;margin-top:6px">
                    <input type="checkbox" id="employee_code_manual" @checked(old('employee_code'))/> Enter my own code instead
                </label>
                <div class="small" id="employee_code_note" style="margin-top:4px">Assigned when you save; it follows the classification below.</div>
            @endif
            @error('employee_code')<div class="field-error">{{ $message }}</div>@enderror
        </div>

        @unless ($employee)
            <script>
                (function () {
                    // The shown code follows the classification, and the manual box
                    // swaps the preview for a field you type yourself (FR-06).
                    var codes = @json($nextCodes);
                    var preview = document.getElementById('employee_code_preview');
                    var real = document.getElementById('employee_code');
                    var manual = document.getElementById('employee_code_manual');
                    var note = document.getElementById('employee_code_note');
                    var classification = document.getElementById('employee_classification');
                    if (!preview || !real || !manual) return;

                    function apply() {
                        var own = manual.checked;
                        preview.hidden = own;
                        real.hidden = !own;
                        if (note) {
                            note.textContent = own
                                ? 'Your code must not already be in use.'
                                : 'Assigned when you save; it follows the classification below.';
                        }
                        if (own) {
                            real.focus();
                        } else {
                            real.value = '';
                        }
                    }

                    if (classification) {
                        classification.addEventListener('change', function () {
                            if (codes[classification.value]) preview.value = codes[classification.value];
                        });
                    }

                    manual.addEventListener('change', apply);
                    apply();
                })();
            </script>
        @endunless
        <div>
            <div class="label-row">
                <label for="department_id">Department</label>
                <x-admin.quick-create id="qc-department" target="department_id" :url="route('admin.master.departments.store')" title="New Department" permission="Departments">
                    <div><label for="qc-dept-name">Department Name *</label><input id="qc-dept-name" name="name" class="input" required/></div>
                    <div><label for="qc-dept-code">Code</label><input id="qc-dept-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div class="full"><label for="qc-dept-description">Description</label><textarea id="qc-dept-description" name="description" class="textarea" rows="2"></textarea></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $employee?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="label-row">
                <label for="designation_id">Designation</label>
                <x-admin.quick-create id="qc-designation" target="designation_id" :url="route('admin.master.designations.store')" title="New Designation" permission="Designations">
                    <div class="full"><label for="qc-desig-name">Designation Name *</label><input id="qc-desig-name" name="name" class="input" required/></div>
                    <div>
                        <label for="qc-desig-department">Department *</label>
                        <select id="qc-desig-department" name="department_id" class="select" data-prefill-from="department_id" required>
                            <option value="">Select...</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="qc-desig-grade">Grade / Level</label>
                        <select id="qc-desig-grade" name="grade" class="select">
                            <option value="">Select...</option>
                            @foreach (['L1', 'L2', 'L3', 'L4', 'L5'] as $grade)
                                <option>{{ $grade }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="designation_id" name="designation_id" class="select">
                <option value="">Select...</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" data-parent="{{ $designation->department_id }}" @selected(old("designation_id", $employee?->designation_id) == $designation->id)>{{ $designation->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="label-row">
                <label for="branch_id">Branch</label>
                <x-admin.quick-create id="qc-branch" target="branch_id" :url="route('admin.master.branches.store')" title="New Branch" permission="Branches">
                    <div><label for="qc-branch-name">Branch Name *</label><input id="qc-branch-name" name="name" class="input" required/></div>
                    <div><label for="qc-branch-code">Code</label><input id="qc-branch-code" name="code" class="input" placeholder="Auto if blank"/></div>
                    <div><label for="qc-branch-city">City</label><input id="qc-branch-city" name="city" class="input" placeholder="Riyadh"/></div>
                    <div><label for="qc-branch-phone">Phone</label><input id="qc-branch-phone" name="phone" class="input" placeholder="+966..."/></div>
                    <input type="hidden" name="status" value="active"/>
                </x-admin.quick-create>
            </div>
            <select id="branch_id" name="branch_id" class="select">
                <option value="">Select...</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_id', $employee?->branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="project_id">Project</label>
            <select id="project_id" name="project_id" class="select">
                <option value="">Head Office</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $employee?->project_id) == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="site_id">Site</label>
            <select id="site_id" name="site_id" class="select">
                <option value="">Select...</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" data-parent="{{ $site->project_id }}" @selected(old("site_id", $employee?->site_id) == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="manager_id">Manager</label>
            <select id="manager_id" name="manager_id" class="select">
                <option value="">Select...</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('manager_id', $employee?->manager_id) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="joining_date">Joining Date</label><input id="joining_date" name="joining_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('joining_date', $employee?->joining_date?->toDateString()) }}"/></div>
        <div>
            <label for="contract_type">Contract Type *</label>
            <select id="contract_type" name="contract_type" class="select" required>
                @foreach ($contractTypes as $type)
                    <option value="{{ $type }}" @selected(old('contract_type', $employee?->contract_type ?? 'Full Time') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="employee_classification">Employee Classification *</label>
            <select id="employee_classification" name="employee_classification" class="select" required>
                @foreach ($classifications as $classification)
                    <option value="{{ $classification }}" @selected(old('employee_classification', $employee?->employee_classification ?? 'Sponsorship') === $classification)>{{ $classification }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="contract_start_date">Contract Start</label><input id="contract_start_date" name="contract_start_date" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old('contract_start_date', $employee?->contract_start_date?->toDateString()) }}"/>@error('contract_start_date')<div class="field-error">{{ $message }}</div>@enderror</div>
        <div><label for="contract_end_date">Contract End</label><input id="contract_end_date" name="contract_end_date" type="date" class="input" value="{{ old('contract_end_date', $employee?->contract_end_date?->toDateString()) }}"/></div>
        <div>
            <label for="annual_leave_entitlement">Annual Leave Entitlement (days / year)</label>
            <input id="annual_leave_entitlement" name="annual_leave_entitlement" type="number" min="0" max="365" class="input" value="{{ old('annual_leave_entitlement', $employee?->annual_leave_entitlement ?? 21) }}"/>
            <div class="small" style="margin-top:4px">Approved Annual Leave is deducted from this on the employee's profile.</div>
        </div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive', 'on leave', 'terminated'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $employee?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="C. Payroll Information" columns="3">
        @if ($employee && auth()->user()->hasPermission('Payroll', 'view'))
            <div class="full help-box" data-salary-context>
                @if ($currentStructure = $employee->activeSalaryStructure)
                    <strong>{{ __('ui.current_salary_structure') }}</strong>:
                    SAR {{ number_format($currentStructure->grossSalary(), 2) }}
                    · {{ $currentStructure->effective_from->toDateString() }}
                    @if ($employee->salaryStructureOutOfDate())
                        <p>{{ __('ui.salary_mismatch') }}</p>
                    @endif
                @else
                    {{ __('ui.salary_created_from_profile') }}
                @endif
                <p>{{ __('ui.salary_history_preserved') }}</p>
                @if (auth()->user()->hasPermission('Payroll', 'create'))
                    <a class="btn sm outline" href="{{ route('admin.hr.salary-structures.create', ['employee' => $employee->id, 'workspace_employee' => $employee->id]) }}">{{ __('ui.new_salary_from_profile') }}</a>
                @endif
            </div>
        @endif
        <div><label for="basic_salary">Basic Salary (SAR) *</label><input id="basic_salary" name="basic_salary" type="number" step="0.01" min="0" class="input" value="{{ old('basic_salary', $employee?->basic_salary ?? 0) }}" required/></div>
        <div><label for="housing_allowance">Housing Allowance</label><input id="housing_allowance" name="housing_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('housing_allowance', $employee?->housing_allowance ?? 0) }}"/></div>
        <div><label for="transport_allowance">Transport Allowance</label><input id="transport_allowance" name="transport_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('transport_allowance', $employee?->transport_allowance ?? 0) }}"/></div>
        <div><label for="food_allowance">Food Allowance</label><input id="food_allowance" name="food_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('food_allowance', $employee?->food_allowance ?? 0) }}"/></div>
        <div><label for="fuel_allowance">Fuel Allowance</label><input id="fuel_allowance" name="fuel_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('fuel_allowance', $employee?->fuel_allowance ?? 0) }}"/></div>
        <div><label for="other_allowance">Other Allowance</label><input id="other_allowance" name="other_allowance" type="number" step="0.01" min="0" class="input" value="{{ old('other_allowance', $employee?->other_allowance ?? 0) }}"/></div>
        <div>
            <label for="payment_method">Payment Method *</label>
            <select id="payment_method" name="payment_method" class="select" required>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method }}" @selected(old('payment_method', $employee?->payment_method ?? 'Bank Transfer') === $method)>{{ $method }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="bank_name">Bank Name</label><input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $employee?->bank_name) }}" placeholder="Al Rajhi Bank"/></div>
        <div><label for="iban">IBAN</label><input id="iban" name="iban" class="input" value="{{ old('iban', $employee?->iban) }}" placeholder="SA00 0000 0000 0000"/></div>
        <div class="full">
            <div class="help-box">
                Enter the pay once, here.
                @if ($employee)
                    Saving creates the employee's first
                    <a href="{{ route('admin.hr.salary-structures.index') }}" style="color:var(--blue);font-weight:700">salary structure</a> from these figures if they have none.
                    An existing structure is left untouched so past payroll stays as it was run; raise a new structure from the employee page after a raise.
                @else
                    Saving creates the employee's first
                    <a href="{{ route('admin.hr.salary-structures.index') }}" style="color:var(--blue);font-weight:700">salary structure</a> from these figures,
                    effective from the contract start date. Further allowance or deduction items can be added to it later.
                @endif
            </div>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="D. Documents & Attachments">
        <div class="help-box">
            IQAMA, passport, insurance and driving licence numbers and expiry dates are kept here, once, together with the file.
            The employee list, the HR dashboard and the <a href="{{ route('admin.hr.documents.index') }}" style="color:var(--blue);font-weight:700">Documents register</a> all read these rows.
            Use <em>Profession / Class</em> for the IQAMA profession or the licence class (private, heavy, light).
        </div>

        <div class="label-row" style="margin-bottom:8px">
            <span class="small"><strong>Document types.</strong> Not in the list? Add your own, for example a Muqeem paper, and it stays available for every employee.</span>
            <x-admin.quick-create id="qc-document-type" target-selector=".js-document-type" :url="route('admin.master.lookup-values.store')" title="New Document Type" permission="HR" submit="Add Document Type">
                <div class="full"><label for="qc-doc-type-value">Document Type *</label><input id="qc-doc-type-value" name="value" class="input" placeholder="e.g. Muqeem Paper" required/></div>
                <input type="hidden" name="type" value="document_type"/>
            </x-admin.quick-create>
        </div>

        @if ($employee && $employee->documents->isNotEmpty())
            <div class="small" style="margin-bottom:6px"><strong>Already attached.</strong> Change the details or choose a new file to renew a document; expiry alerts follow these values.</div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width:150px">Document Type</th>
                            <th style="min-width:150px">Profession / Class</th>
                            <th style="min-width:140px">Number</th>
                            <th style="width:160px">Issue Date</th>
                            <th style="width:160px">Expiry Date</th>
                            <th>Validity</th>
                            <th style="min-width:200px">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employee->documents as $document)
                            @php $key = "existing_documents.{$document->id}"; @endphp
                            <tr>
                                <td>{{ $document->document_type }}</td>
                                <td><input name="existing_documents[{{ $document->id }}][document_subtype]" class="input" list="document-subtypes" value="{{ old("$key.document_subtype", $document->document_subtype) }}" placeholder="Profession / licence class"/></td>
                                <td><input name="existing_documents[{{ $document->id }}][document_number]" class="input" value="{{ old("$key.document_number", $document->document_number) }}"/></td>
                                <td><input name="existing_documents[{{ $document->id }}][issue_date]" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old("$key.issue_date", $document->issue_date?->toDateString()) }}"/></td>
                                <td><input name="existing_documents[{{ $document->id }}][expiry_date]" type="date" class="input" value="{{ old("$key.expiry_date", $document->expiry_date?->toDateString()) }}"/></td>
                                <td><x-admin.status-badge :status="$document->validityStatus()"/></td>
                                <td>
                                    @if ($document->file_path)
                                        <a href="{{ route('admin.hr.documents.view', $document) }}" target="_blank" rel="noopener" style="color:var(--blue);font-weight:700">View</a>
                                        <span class="small">·</span>
                                        <a href="{{ route('admin.hr.documents.download', $document) }}" style="color:var(--blue);font-weight:700">Download</a>
                                    @else
                                        <span class="small">No file yet</span>
                                    @endif
                                    <input name="existing_documents[{{ $document->id }}][file]" type="file" class="input js-document-file" accept=".pdf,.jpg,.jpeg,.png,.webp" title="Choose a file to replace the current one" style="margin-top:4px"/>
                                    <div class="small js-file-preview" style="margin-top:4px" hidden></div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('existing_documents.*.file')<div class="field-error">{{ $message }}</div>@enderror
            @error('existing_documents.*.expiry_date')<div class="field-error">{{ $message }}</div>@enderror
            <br/>
            <div class="small" style="margin-bottom:6px"><strong>Add documents</strong></div>
        @endif

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:150px">Document Type</th>
                        <th style="min-width:150px">Profession / Class</th>
                        <th style="min-width:140px">Number</th>
                        <th style="width:160px">Issue Date</th>
                        <th style="width:160px">Expiry Date</th>
                        <th style="min-width:200px">File</th>
                        <th style="width:40px"></th>
                    </tr>
                </thead>
                <tbody id="document-rows">
                    @for ($i = 0; $i < $documentRows; $i++)
                        @include('admin.hr.employees._document-row', ['i' => $i])
                    @endfor
                </tbody>
            </table>
        </div>
        <template id="document-row-template">
            @include('admin.hr.employees._document-row', ['i' => '__INDEX__'])
        </template>
        <datalist id="document-subtypes">
            @foreach ($documentSubtypes as $subtype)
                <option value="{{ $subtype }}"></option>
            @endforeach
        </datalist>
        <div class="dynamic-rows-actions">
            <button type="button" class="btn outline" id="add-document-row">+ Add Document</button>
            <span class="small">Attach IQAMA, passport, contract, medical insurance, driving licence or any other file. Rows without a document type are ignored.</span>
        </div>

        <script>
            (function () {
                // Show the chosen file, with an Open link, before anything is saved,
                // so a wrong attachment is caught here rather than after Save (FR-03).
                document.addEventListener('change', function (event) {
                    var input = event.target.closest('.js-document-file');
                    if (!input) return;

                    var box = input.parentElement.querySelector('.js-file-preview');
                    if (!box) return;

                    if (box.dataset.url) {
                        URL.revokeObjectURL(box.dataset.url);
                        delete box.dataset.url;
                    }

                    var file = input.files && input.files[0];
                    if (!file) {
                        box.hidden = true;
                        box.textContent = '';
                        return;
                    }

                    var url = URL.createObjectURL(file);
                    box.dataset.url = url;
                    box.innerHTML = '';

                    var name = document.createElement('span');
                    name.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB) ';
                    box.appendChild(name);

                    var link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener';
                    link.style.color = 'var(--blue)';
                    link.style.fontWeight = '700';
                    link.textContent = 'Open to check';
                    box.appendChild(link);

                    box.hidden = false;
                });
            })();
        </script>
        @error('documents.*.file')<div class="field-error">{{ $message }}</div>@enderror
        @error('documents.*.issue_date')<div class="field-error">{{ $message }}</div>@enderror
        @error('documents.*.expiry_date')<div class="field-error">{{ $message }}</div>@enderror
    </x-admin.form-section>

    <x-admin.form-section title="E. Access" columns="3">
        <div>
            <label for="user_id">Link User Account</label>
            <select id="user_id" name="user_id" class="select">
                <option value="">No linked account</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('user_id', $employee?->user_id) == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="mobile_access">Mobile App Access</label>
            <select id="mobile_access" name="mobile_access" class="select">
                <option value="1" @selected(old('mobile_access', $employee?->mobile_access ?? false))>Yes</option>
                <option value="0" @selected(! old('mobile_access', $employee?->mobile_access ?? false))>No</option>
            </select>
        </div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.employees.index') }}">Cancel</a>
        <button type="submit" name="_save_action" value="stay" class="btn outline" data-save-default>{{ __('ui.save_stay') }}</button>
        <button type="submit" name="_save_action" value="next" class="btn outline">{{ __('ui.save_next') }}</button>
        <button type="submit" class="btn primary">{{ $employee ? 'Update Employee' : 'Save Employee' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="department_id" child="designation_id" placeholder="designations"/>
<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
<x-admin.dynamic-rows body="document-rows" template="document-row-template" add="add-document-row" min="1"/>
