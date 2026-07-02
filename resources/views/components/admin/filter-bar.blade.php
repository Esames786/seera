@props(['action' => null])

<form method="GET" @if($action) action="{{ $action }}" @endif class="toolbar">
    <div class="toolbar-left">{{ $slot }}</div>
    <div class="toolbar-right">
        <button type="submit" class="btn outline">Filter</button>
        {{ $actions ?? '' }}
    </div>
</form>
