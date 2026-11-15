@if ($paginator->hasPages())
    <div class="ui-pager">
        <span>Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}</span>
        <nav aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="pg off"><i class="bi bi-chevron-left"></i></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" aria-label="Previous"><i class="bi bi-chevron-left"></i></a>
            @endif

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if ($page === $paginator->currentPage())
                    <span class="pg on">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" aria-label="Next"><i class="bi bi-chevron-right"></i></a>
            @else
                <span class="pg off"><i class="bi bi-chevron-right"></i></span>
            @endif
        </nav>
    </div>
@endif
