{{-- One new attachment row; $i is the index or the literal __INDEX__ inside the add-row template. --}}
<tr data-row-index="{{ $i }}">
    <td>
        <select name="documents[{{ $i }}][document_type]" class="select">
            <option value="">Select type...</option>
            @foreach (\App\Models\EmployeeDocument::TYPES as $type)
                <option value="{{ $type }}" @selected(old("documents.$i.document_type") === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </td>
    <td><input name="documents[{{ $i }}][document_subtype]" class="input" list="document-subtypes" value="{{ old("documents.$i.document_subtype") }}" placeholder="Profession / licence class"/></td>
    <td><input name="documents[{{ $i }}][document_number]" class="input" value="{{ old("documents.$i.document_number") }}"/></td>
    <td><input name="documents[{{ $i }}][issue_date]" type="date" class="input" max="{{ now()->toDateString() }}" value="{{ old("documents.$i.issue_date") }}"/></td>
    <td><input name="documents[{{ $i }}][expiry_date]" type="date" class="input" value="{{ old("documents.$i.expiry_date") }}"/></td>
    <td><input name="documents[{{ $i }}][file]" type="file" class="input" accept=".pdf,.jpg,.jpeg,.png,.webp"/></td>
    <td><button type="button" class="row-remove" title="Remove row" aria-label="Remove row">&times;</button></td>
</tr>
