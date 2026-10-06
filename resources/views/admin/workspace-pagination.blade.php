<div class="table-footer"><span>{{ __('workspace.records') }}: {{ $rows->total() }}</span>
@if(request()->wantsJson())
    @if($rows->previousPageUrl())<button type="button" class="btn outline" data-panel-load="{{ $rows->previousPageUrl() }}">{{ __('workspace.previous') }}</button>@endif
    @if($rows->nextPageUrl())<button type="button" class="btn outline" data-panel-load="{{ $rows->nextPageUrl() }}">{{ __('workspace.next') }}</button>@endif
@else {{ $rows->links() }} @endif
</div>
