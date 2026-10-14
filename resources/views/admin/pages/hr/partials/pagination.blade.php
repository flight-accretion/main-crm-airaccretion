@if($pager->hasPages())
    <nav class="hr-pagination" aria-label="Employee pages">
        @if(!$pager->onFirstPage())<a href="{{ $pager->previousPageUrl() }}" rel="prev">Previous</a>@endif
        <span>{{ $pager->firstItem() }}-{{ $pager->lastItem() }} of {{ $pager->total() }}</span>
        @if($pager->hasMorePages())<a href="{{ $pager->nextPageUrl() }}" rel="next">Next</a>@endif
    </nav>
@endif
