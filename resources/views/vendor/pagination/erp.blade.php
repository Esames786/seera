@if ($paginator->hasPages())
    <nav class="actions" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="btn sm" style="opacity:.5;cursor:default">&laquo; Previous</span>
        @else
            <a class="btn sm outline" href="{{ $paginator->previousPageUrl() }}" rel="prev">&laquo; Previous</a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page == $paginator->currentPage())
                <span class="btn sm primary">{{ $page }}</span>
            @else
                <a class="btn sm outline" href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="btn sm outline" href="{{ $paginator->nextPageUrl() }}" rel="next">Next &raquo;</a>
        @else
            <span class="btn sm" style="opacity:.5;cursor:default">Next &raquo;</span>
        @endif
    </nav>
@endif
