@props(['title' => null, 'subtitle' => null])

<div class="table-card">
    @if ($title)
        <div class="table-title">
            <span>{{ $title }} @if($subtitle)<span class="small">{{ $subtitle }}</span>@endif</span>
            {{ $headerActions ?? '' }}
        </div>
    @endif
    <div class="table-wrap">
        <table {{ $attributes }}>
            {{ $slot }}
        </table>
    </div>
    @isset($footer)
        <div class="table-footer">{{ $footer }}</div>
    @endisset
</div>
