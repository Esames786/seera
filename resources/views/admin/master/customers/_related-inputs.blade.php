@php
    $canAddRelated = auth()->user()->hasPermission('Customers', 'create');
    $canDeleteRelated = auth()->user()->hasPermission('Customers', 'delete');
@endphp
@foreach (['office', 'site'] as $kind)
    <x-admin.form-section :title="__('ui.'.$kind.'_contacts')">
        @if ($customer && $customer->contacts->where('location_type', $kind)->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <thead><tr><th>{{ __('ui.contact_name') }}</th><th>{{ __('ui.phone') }}</th><th>{{ __('ui.email') }}</th><th>{{ __('ui.address') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($customer->contacts->where('location_type', $kind) as $contact)
                            <tr>
                                <td>{{ $contact->name }}<div class="small">{{ $contact->title }}</div></td>
                                <td><bdi>{{ $contact->phone }}</bdi></td><td><bdi>{{ $contact->email }}</bdi></td>
                                <td>{{ $contact->address }} @if($contact->site)<div class="small">{{ $contact->site->name }}</div>@endif</td>
                                <td>@if($canDeleteRelated)<button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.customers.contacts.destroy', [$customer, $contact]) }}" data-delete-name="{{ $contact->name }}">{{ __('ui.remove') }}</button>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="small">{{ __('ui.empty_contacts') }}</p>
        @endif
        @if ($canAddRelated)
            <p class="small">{{ __('ui.contact_help') }}</p>
            <div class="form-grid three">
                @foreach (['name' => 'contact_name', 'title' => 'title', 'phone' => 'phone', 'email' => 'email', 'address' => 'address'] as $field => $translation)
                    <div>
                        <label for="new-{{ $kind }}-{{ $field }}">{{ __('ui.'.$translation) }}</label>
                        <input id="new-{{ $kind }}-{{ $field }}" name="new_contacts[{{ $kind }}][{{ $field }}]" type="{{ $field === 'email' ? 'email' : 'text' }}" class="input" value="{{ old('new_contacts.'.$kind.'.'.$field) }}"/>
                        @error('new_contacts.'.$kind.'.'.$field)<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                @if ($kind === 'site')
                    <div>
                        <label for="new-site-id">{{ __('ui.site') }}</label>
                        <select id="new-site-id" name="new_contacts[site][site_id]" class="select">
                            <option value="">{{ __('ui.no_site') }}</option>
                            @foreach ($sites as $site)<option value="{{ $site->id }}" @selected(old('new_contacts.site.site_id') == $site->id)>{{ $site->name }}</option>@endforeach
                        </select>
                        @error('new_contacts.site.site_id')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
        @endif
    </x-admin.form-section>
@endforeach
<x-admin.form-section :title="__('ui.notes')">
    @forelse ($customer?->notes ?? [] as $note)
        <div class="note" style="margin-bottom:10px">
            <div style="white-space:pre-line">{{ $note->note }}</div>
            <div class="small">{{ $note->user?->name }} · {{ $note->created_at->format('d M Y H:i') }}</div>
            @if ($canDeleteRelated)
                <button type="button" class="btn sm danger js-delete" data-delete-url="{{ route('admin.master.customers.notes.destroy', [$customer, $note]) }}" data-delete-name="this note">{{ __('ui.remove') }}</button>
            @endif
        </div>
    @empty
        <p class="small">{{ __('ui.empty_notes') }}</p>
    @endforelse
    @if ($canAddRelated)
        <label for="new-note">{{ __('ui.new_note') }}</label>
        <textarea id="new-note" name="new_note" class="textarea" maxlength="2000">{{ old('new_note') }}</textarea>
        @error('new_note')<div class="field-error">{{ $message }}</div>@enderror
    @endif
</x-admin.form-section>
