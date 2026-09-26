@props([
    'cancel',                 // list URL used when no origin is known
    'saveNew' => false,       // offer "Save & New" (repeatable data entry only)
    'closeLabel' => null,     // defaults to "Save & close"
    'saveLabel' => null,      // defaults to "Save"
    'newLabel' => null,       // defaults to "Save & new"
])
@php
    $returnTo = old(\App\Support\SaveAction::RETURN_FIELD, \App\Support\SaveAction::returnTo());
@endphp
<div {{ $attributes->merge(['class' => 'form-actions']) }}>
    @if ($returnTo)
        <input type="hidden" name="{{ \App\Support\SaveAction::RETURN_FIELD }}" value="{{ $returnTo }}"/>
    @endif
    <a class="btn outline" href="{{ $returnTo ?: $cancel }}">{{ __('ui.cancel') }}</a>
    {{ $slot }}
    <button type="submit" name="{{ \App\Support\SaveAction::FIELD }}" value="{{ \App\Support\SaveAction::STAY }}" class="btn outline" data-save-default>{{ $saveLabel ?? __('ui.save') }}</button>
    @if ($saveNew)
        <button type="submit" name="{{ \App\Support\SaveAction::FIELD }}" value="{{ \App\Support\SaveAction::NEW }}" class="btn outline">{{ $newLabel ?? __('ui.save_new') }}</button>
    @endif
    <button type="submit" name="{{ \App\Support\SaveAction::FIELD }}" value="{{ \App\Support\SaveAction::CLOSE }}" class="btn primary">{{ $closeLabel ?? __('ui.save_close') }}</button>
</div>
