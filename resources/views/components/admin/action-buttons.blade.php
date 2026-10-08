@props(['view' => null, 'edit' => null, 'delete' => null, 'name' => 'this record', 'editLabel' => 'Edit', 'deleteLabel' => 'Delete', 'deactivate' => false, 'deactivateHelp' => null])

<div class="actions">
    @if ($view)
        <a class="btn sm primary" href="{{ $view }}">View</a>
    @endif
    @if ($edit)
        <a class="btn sm" href="{{ $edit }}">{{ $editLabel }}</a>
    @endif
    @if ($delete)
        <button type="button" class="btn sm danger js-delete" data-delete-url="{{ $delete }}" data-delete-name="{{ $name }}" @if($deactivate) data-deactivate="1" @endif @if($deactivateHelp) data-deactivate-help="{{ $deactivateHelp }}" @endif>{{ $deleteLabel }}</button>
    @endif
    {{ $slot }}
</div>
