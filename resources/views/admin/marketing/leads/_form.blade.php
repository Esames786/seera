@php
    /** @var \App\Models\MarketingLead|null $lead */
    $lead = $lead ?? null;
@endphp

<form method="POST" action="{{ $lead ? route('admin.marketing.leads.update', $lead) : route('admin.marketing.leads.store') }}">
    @csrf
    @if ($lead) @method('PUT') @endif

    <x-admin.form-section title="A. Prospect" columns="3">
        <div><label for="company_name">Company / Client *</label><input id="company_name" name="company_name" class="input" value="{{ old('company_name', $lead?->company_name) }}" required/>@error('company_name')<div class="field-error">{{ $message }}</div>@enderror</div>
        <div><label for="contact_name">Contact Person</label><input id="contact_name" name="contact_name" class="input" value="{{ old('contact_name', $lead?->contact_name) }}"/></div>
        <div><label for="contact_title">Contact Title</label><input id="contact_title" name="contact_title" class="input" value="{{ old('contact_title', $lead?->contact_title) }}" placeholder="e.g. Project Manager"/></div>
        <div><label for="contact_phone">Phone</label><input id="contact_phone" name="contact_phone" class="input" value="{{ old('contact_phone', $lead?->contact_phone) }}"/></div>
        <div><label for="contact_email">Email</label><input id="contact_email" name="contact_email" type="email" class="input" value="{{ old('contact_email', $lead?->contact_email) }}"/>@error('contact_email')<div class="field-error">{{ $message }}</div>@enderror</div>
        <div><label for="city">City</label><input id="city" name="city" class="input" value="{{ old('city', $lead?->city) }}"/></div>
        <div class="full"><label for="location">Location / Address</label><input id="location" name="location" class="input" value="{{ old('location', $lead?->location) }}" placeholder="Where the visits happen"/></div>
        <div>
            <label for="source">Source</label>
            <select id="source" name="source" class="select">
                <option value="">Select...</option>
                @foreach ($sources as $source)
                    <option value="{{ $source }}" @selected(old('source', $lead?->source) === $source)>{{ $source }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="requirement">Requirement</label><input id="requirement" name="requirement" class="input" value="{{ old('requirement', $lead?->requirement) }}" placeholder="e.g. Villa compound, 12 units"/></div>
        <div><label for="estimated_value">Estimated Value (SAR)</label><input id="estimated_value" name="estimated_value" type="number" step="0.01" min="0" class="input" value="{{ old('estimated_value', $lead?->estimated_value) }}"/></div>
    </x-admin.form-section>

    <x-admin.form-section title="B. Assignment &amp; Follow-up" columns="3">
        @if ($isManager)
            <div>
                <label for="assigned_to">Assigned To</label>
                <select id="assigned_to" name="assigned_to" class="select">
                    <option value="">Unassigned</option>
                    @foreach ($staff as $member)
                        <option value="{{ $member->id }}" @selected(old('assigned_to', $lead?->assigned_to) == $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
                <div class="small" style="margin-top:4px">Only users with Marketing access are listed.</div>
            </div>
            @if ($lead)
                <div>
                    <label for="status">Status</label>
                    <select id="status" name="status" class="select">
                        @foreach ($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(old('status', $lead->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="small" style="margin-top:4px">Normally moved by recorded visits; change here to reopen or close manually.</div>
                </div>
            @endif
        @else
            <div>
                <label>Assigned To</label>
                <input class="input" value="{{ $lead?->assignee?->name ?? auth()->user()->name }}" disabled/>
                <div class="small" style="margin-top:4px">Assignment is set by the marketing manager.</div>
            </div>
        @endif
        <div><label for="next_follow_up_date">Next Follow-up</label><input id="next_follow_up_date" name="next_follow_up_date" type="date" class="input" value="{{ old('next_follow_up_date', $lead?->next_follow_up_date?->toDateString()) }}"/></div>
        <div class="full"><label for="notes">Notes</label><textarea id="notes" name="notes" class="textarea">{{ old('notes', $lead?->notes) }}</textarea></div>
    </x-admin.form-section>

    <div class="form-actions">
        <a class="btn outline" href="{{ $lead ? route('admin.marketing.leads.show', $lead) : route('admin.marketing.leads.index') }}">Cancel</a>
        <button type="submit" class="btn primary">{{ $lead ? 'Update Lead' : 'Save Lead' }}</button>
    </div>
</form>
