@props(['owner' => null, 'kind'])
@php $label = $kind === 'customer' ? __('Customer Code') : __('Supplier Code'); @endphp
<div data-master-code>
    <label for="{{ $owner || filled(old('code')) ? 'code' : 'code-preview' }}" data-code-label>{{ $label }}</label>
    @if ($owner)
        <input id="code" name="code" class="input" value="{{ old('code', $owner->code) }}" autocomplete="off"/>
    @else
        <input id="code-preview" class="input" data-code-preview value="{{ \App\Support\CodeGenerator::sequential($kind.'s', 'code', $kind === 'customer' ? 'CUS-' : 'SUP-') }}" readonly aria-label="{{ __('feedback.automatic_code') }}" @if(filled(old('code'))) hidden @endif/>
        <label><input type="checkbox" data-code-manual @checked(filled(old('code')))/> {{ __('feedback.manual_code') }}</label>
        <input id="code" name="code" class="input" value="{{ old('code') }}" autocomplete="off" data-code-input @if(blank(old('code'))) hidden @endif/>
        <div class="small" data-code-help @if(filled(old('code'))) hidden @endif>{{ __('feedback.code_help') }}</div>
    @endif
    @error('code')<div class="field-error">{{ $message }}</div>@enderror
</div>
