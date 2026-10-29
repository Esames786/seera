@props(['view' => null, 'edit' => null, 'delete' => null, 'name' => 'this record', 'editLabel' => 'Edit', 'deleteLabel' => 'Delete', 'deactivate' => false, 'deactivateHelp' => null])

<div class="actions">
    @if ($view)
        <a class="btn sm primary" href="{{ $view }}">{{ __('View') }}</a>
    @endif
    @if ($edit)
        <a class="btn sm" href="{{ $edit }}">{{ __($editLabel) }}</a>
    @endif
    @if ($delete)
        <button type="button" class="btn sm danger js-delete" data-delete-url="{{ $delete }}" data-delete-name="{{ __($name) }}" @if($deactivate) data-deactivate="1" @endif @if($deactivateHelp) data-deactivate-help="{{ __($deactivateHelp) }}" @endif>{{ __($deleteLabel) }}</button>
    @endif
    {{ $slot }}
</div>
