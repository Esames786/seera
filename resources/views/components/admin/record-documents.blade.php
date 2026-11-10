@props(['owner' => null, 'kind', 'readonly' => false])
@php $documentLabels = $kind === 'equipment' ? \App\Models\EquipmentDocument::TYPES : ['other' => 'Other', 'cr' => 'Commercial Registration', 'vat' => 'VAT Certificate', 'national_address' => 'National Address', 'contract' => 'Contract', 'purchase_order' => 'Purchase Order', 'application' => 'Application']; @endphp
<div id="documents" class="form-section" data-document-inputs>
    <div class="section-title">{{ __('Documents') }}</div>
    <div class="section-body">
        <p class="small">{{ __('feedback.documents_help') }}</p>
        @if ($owner)
            <div style="max-width:100%;overflow-x:auto">
                <table class="table"><thead><tr><th>{{ __('Type') }}</th><th>{{ __('File') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
                @forelse ($owner->documents as $document)
                    @php $url = $kind === 'equipment' ? route('admin.equipment.documents.file', [$owner, $document->id]) : route('admin.master.'.$kind.'s.documents.show', [$owner, $document->id]); @endphp
                    <tr><td>{{ __($documentLabels[$document->document_type] ?? $document->document_type) }}</td><td style="overflow-wrap:anywhere">{{ $document->original_filename }}</td><td>{{ $document->expiry_date?->format('Y-m-d') ?? '-' }}</td><td><a class="btn sm outline" href="{{ $url }}" target="_blank" rel="noopener">{{ __('View') }}</a> <a class="btn sm outline" href="{{ $url.'?download=1' }}">{{ __('Download') }}</a></td></tr>
                @empty
                    <tr><td colspan="4">{{ __('feedback.no_documents') }}</td></tr>
                @endforelse
                </tbody></table>
            </div>
        @endif
        @unless ($readonly)
            <p>{{ __('feedback.upload_with_save') }}</p>
            @foreach (old('new_documents', [[]]) as $index => $row)
                <div class="form-grid three" data-document-row style="border-block-start:1px solid #dfe5ee;padding-block:12px">
                    <div><label>{{ __('Type') }}</label><select class="select" name="new_documents[{{ $index }}][document_type]">
                        @foreach ($documentLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($row['document_type'] ?? 'other') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select></div>
                    <div><label>{{ __('Document Number') }}</label><input class="input" name="new_documents[{{ $index }}][document_number]" value="{{ $row['document_number'] ?? '' }}" maxlength="{{ $kind === 'equipment' ? 80 : 100 }}"/></div>
                    <div><label>{{ __('File') }}</label><input class="input" type="file" name="new_documents[{{ $index }}][file]" accept="application/pdf,image/jpeg,image/png,image/webp"/></div>
                    <div><label>{{ __('Issue Date') }}</label><input class="input" type="date" name="new_documents[{{ $index }}][issue_date]" value="{{ $row['issue_date'] ?? '' }}"/></div>
                    <div><label>{{ __('Expiry Date') }}</label><input class="input" type="date" name="new_documents[{{ $index }}][expiry_date]" value="{{ $row['expiry_date'] ?? '' }}"/></div>
                    <button class="btn outline" type="button" data-remove-document>{{ __('Remove') }}</button>
                </div>
            @endforeach
            <button class="btn outline" type="button" data-add-document>{{ __('feedback.add_document') }}</button>
            <p class="small">{{ __('feedback.document_limits') }}</p>
            @foreach ($errors->get('new_documents.*') as $messages) @foreach ($messages as $message)<div class="field-error">{{ $message }}</div>@endforeach @endforeach
        @endunless
    </div>
</div>
