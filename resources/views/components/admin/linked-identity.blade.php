@props(['link', 'title'])
<section class="card" style="padding:16px;margin-bottom:16px;overflow-wrap:anywhere" data-linked-identity>
    <h3>{{ $title }}</h3>
    @if($link['state'] === 'linked')
        <p>{{ $link['name'] }} <x-admin.status-badge :status="$link['status']"/></p>
        <div class="actions" style="flex-wrap:wrap">
            @if($link['view_url'])<a class="btn outline" data-linked-view href="{{ $link['view_url'] }}">{{ __('ui.view_linked_record') }}</a>@endif
            @if($link['edit_url'])<a class="btn outline" data-linked-edit href="{{ $link['edit_url'] }}">{{ __('ui.edit_linked_record') }}</a>@endif
        </div>
    @else
        <p class="small">{{ __('ui.link_'.$link['state']) }}</p>
    @endif
</section>
