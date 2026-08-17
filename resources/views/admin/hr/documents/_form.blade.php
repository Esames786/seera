@php /** @var \App\Models\EmployeeDocument|null $document */ $document = $document ?? null; @endphp

<form method="POST" action="{{ $document ? route('admin.hr.documents.update', $document) : route('admin.hr.documents.store') }}" enctype="multipart/form-data">
    @csrf
    @if ($document) @method('PUT') @endif

    <x-admin.form-section title="Document Information" columns="3">
        <div>
            <label for="employee_id">Employee *</label>
            <select id="employee_id" name="employee_id" class="select" required>
                <option value="">Select...</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(old('employee_id', $document?->employee_id ?? request('employee')) == $employee->id)>{{ $employee->employee_code }} - {{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="document_type">Document Type *</label>
            <select id="document_type" name="document_type" class="select" required>
                @foreach ($documentTypes as $type)
                    <option value="{{ $type }}" @selected(old('document_type', $document?->document_type) === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="document_number">Document Number</label><input id="document_number" name="document_number" class="input" value="{{ old('document_number', $document?->document_number) }}"/></div>
        <div><label for="issue_date">Issue Date</label><input id="issue_date" name="issue_date" type="date" class="input" value="{{ old('issue_date', $document?->issue_date?->toDateString()) }}"/></div>
        <div><label for="expiry_date">Expiry Date</label><input id="expiry_date" name="expiry_date" type="date" class="input" value="{{ old('expiry_date', $document?->expiry_date?->toDateString()) }}"/></div>
        <div>
            <label for="status">Status *</label>
            <select id="status" name="status" class="select" required>
                @foreach (['active', 'inactive'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $document?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="file">Document Upload</label>
            <input id="file" name="file" type="file" class="input"/>
            @if ($document?->file_path)
                <div class="small">Current file: <a href="{{ asset('storage/'.$document->file_path) }}" style="color:var(--blue);font-weight:700">view</a></div>
            @endif
        </div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $document?->notes) }}</textarea></div>
    </x-admin.form-section>

    <div class="help-box">
        Validity is derived from the expiry date: expired, expiring soon (within 60 days) or valid.
    </div>

    <div class="form-actions">
        <a class="btn outline" href="{{ route('admin.hr.documents.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $document ? 'Update Document' : 'Save Document' }}</button>
    </div>
</form>
