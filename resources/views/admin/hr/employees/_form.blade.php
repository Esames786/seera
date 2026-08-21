@php /** @var \App\Models\Employee|null $employee */ $employee = $employee ?? null; @endphp

<form method="POST" action="{{ $employee ? route("admin.hr.employees.update", $employee) : route("admin.hr.employees.store") }}" enctype="multipart/form-data">
    @csrf
    @if ($employee) @method('PUT') @endif

    <x-admin.form-section title="A. Personal Information" columns="3">
        <div><label for="first_name">First Name *</label><input id="first_name" name="first_name" class="input" value="{{ old('first_name', $employee?->first_name) }}" required/></div>
        <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" class="input" value="{{ old('last_name', $employee?->last_name) }}"/></div>
        <div>
            <label for="nationality">Nationality</label>
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
        <div><label for="employee_code">Employee Code *</label><input id="employee_code" name="employee_code" class="input" value="{{ old('employee_code', $employee?->employee_code) }}" placeholder="EMP-014" required/></div>
        <div>
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id" class="select">
                <option value="">Select...</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $employee?->department_id) == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="designation_id">Designation</label>
            <select id="designation_id" name="designation_id" class="select">
                <option value="">Select...</option>
                @foreach ($designations as $designation)
                    <option value="{{ $designation->id }}" data-parent="{{ $designation->department_id }}" @selected(old("designation_id", $employee?->designation_id) == $designation->id)>{{ $designation->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="branch_id">Branch</label>
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
        <div><label for="joining_date">Joining Date</label><input id="joining_date" name="joining_date" type="date" class="input" value="{{ old('joining_date', $employee?->joining_date?->toDateString()) }}"/></div>
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
        <div><label for="contract_start_date">Contract Start</label><input id="contract_start_date" name="contract_start_date" type="date" class="input" value="{{ old('contract_start_date', $employee?->contract_start_date?->toDateString()) }}"/></div>
        <div><label for="contract_end_date">Contract End</label><input id="contract_end_date" name="contract_end_date" type="date" class="input" value="{{ old('contract_end_date', $employee?->contract_end_date?->toDateString()) }}"/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive', 'on leave', 'terminated'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $employee?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="C. Documents" columns="3">
        <div><label for="iqama_number">IQAMA Number</label><input id="iqama_number" name="iqama_number" class="input" value="{{ old('iqama_number', $employee?->iqama_number) }}" placeholder="245XXXXXXX"/></div>
        <div><label for="iqama_expiry_date">IQAMA Expiry Date</label><input id="iqama_expiry_date" name="iqama_expiry_date" type="date" class="input" value="{{ old('iqama_expiry_date', $employee?->iqama_expiry_date?->toDateString()) }}"/></div>
        <div><label for="passport_number">Passport Number</label><input id="passport_number" name="passport_number" class="input" value="{{ old('passport_number', $employee?->passport_number) }}" placeholder="AB1234567"/></div>
        <div><label for="passport_expiry_date">Passport Expiry Date</label><input id="passport_expiry_date" name="passport_expiry_date" type="date" class="input" value="{{ old('passport_expiry_date', $employee?->passport_expiry_date?->toDateString()) }}"/></div>
        <div><label for="insurance_number">Insurance Number</label><input id="insurance_number" name="insurance_number" class="input" value="{{ old('insurance_number', $employee?->insurance_number) }}" placeholder="INS-000000"/></div>
        <div><label for="insurance_expiry_date">Insurance Expiry Date</label><input id="insurance_expiry_date" name="insurance_expiry_date" type="date" class="input" value="{{ old('insurance_expiry_date', $employee?->insurance_expiry_date?->toDateString()) }}"/></div>
        <div><label for="driving_license_number">Driving License Number</label><input id="driving_license_number" name="driving_license_number" class="input" value="{{ old('driving_license_number', $employee?->driving_license_number) }}" placeholder="DL-000000"/></div>
        <div><label for="driving_license_expiry_date">Driving License Expiry</label><input id="driving_license_expiry_date" name="driving_license_expiry_date" type="date" class="input" value="{{ old('driving_license_expiry_date', $employee?->driving_license_expiry_date?->toDateString()) }}"/></div>
        <div class="full">
            <div class="help-box">
                Numbers and expiry dates are kept here. Upload the files in Section E below. The
                <a href="{{ route('admin.hr.documents.index') }}" style="color:var(--blue);font-weight:700">Employee Documents</a>
                screen is a read-only register of IQAMA, passport, contract, insurance, driving license and other files.
            </div>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="D. Payroll Information" columns="3">
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
                These allowances are the employee default. The payroll run uses the active
                <a href="{{ route('admin.hr.salary-structures.index') }}" style="color:var(--blue);font-weight:700">salary structure</a>,
                which is pre-filled from these values and can add further allowance or deduction items.
            </div>
        </div>
    </x-admin.form-section>

    <x-admin.form-section title="E. Document Attachments">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="min-width:170px">Document Type</th>
                        <th style="min-width:150px">Number</th>
                        <th style="width:160px">Issue Date</th>
                        <th style="width:160px">Expiry Date</th>
                        <th style="min-width:200px">File</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < 4; $i++)
                        <tr>
                            <td>
                                <select name="documents[{{ $i }}][document_type]" class="select">
                                    <option value="">Select type...</option>
                                    @foreach (\App\Models\EmployeeDocument::TYPES as $type)
                                        <option value="{{ $type }}" @selected(old("documents.$i.document_type") === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input name="documents[{{ $i }}][document_number]" class="input" value="{{ old("documents.$i.document_number") }}"/></td>
                            <td><input name="documents[{{ $i }}][issue_date]" type="date" class="input" value="{{ old("documents.$i.issue_date") }}"/></td>
                            <td><input name="documents[{{ $i }}][expiry_date]" type="date" class="input" value="{{ old("documents.$i.expiry_date") }}"/></td>
                            <td><input name="documents[{{ $i }}][file]" type="file" class="input"/></td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>
        <div class="small" style="margin-top:10px">
            Attach IQAMA, passport, contract, medical insurance, driving license or any other file here. Rows without a document type are ignored.
        </div>

        @if ($employee && $employee->documents->isNotEmpty())
            <br/>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Already Attached</th><th>Number</th><th>Expiry</th><th>Validity</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($employee->documents as $document)
                            <tr>
                                <td>{{ $document->document_type }}</td>
                                <td>{{ $document->document_number ?? '-' }}</td>
                                <td>{{ $document->expiry_date?->toDateString() ?? '-' }}</td>
                                <td><x-admin.status-badge :status="$document->validityStatus()"/></td>
                                <td>
                                    @if ($document->file_path)
                                        <a href="{{ route('admin.hr.documents.download', $document) }}" style="color:var(--blue);font-weight:700">Download</a>
                                    @else
                                        <span class="small">Not uploaded</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-admin.form-section>

    <x-admin.form-section title="F. Access" columns="3">
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
        <button type="submit" class="btn primary">{{ $employee ? 'Update Employee' : 'Save Employee' }}</button>
    </div>
</form>

<x-admin.dependent-select parent="department_id" child="designation_id" placeholder="designations"/>
<x-admin.dependent-select parent="project_id" child="site_id" placeholder="sites"/>
