@props(['view' => null, 'edit' => null, 'delete' => null, 'name' => 'this record'])

<div class="actions">
    @if ($view)
        <a class="btn sm primary" href="{{ $view }}">View</a>
    @endif
    @if ($edit)
        <a class="btn sm" href="{{ $edit }}">Edit</a>
    @endif
    @if ($delete)
        <button type="button" class="btn sm danger js-delete" data-delete-url="{{ $delete }}" data-delete-name="{{ $name }}">Delete</button>
    @endif
    {{ $slot }}
</div>
