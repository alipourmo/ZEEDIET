@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="pager" aria-label="{{ __('common.pagination') }}">
        @if ($paginator->onFirstPage())
            <span class="pager__link is-disabled">{{ __('common.previous') }}</span>
        @else
            <a class="pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('common.previous') }}</a>
        @endif

        <span class="pager__info">{{ __('common.page_of', ['page' => \App\Support\Display::fa($paginator->currentPage()), 'last' => \App\Support\Display::fa($paginator->lastPage())]) }}</span>

        @if ($paginator->hasMorePages())
            <a class="pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('common.next') }}</a>
        @else
            <span class="pager__link is-disabled">{{ __('common.next') }}</span>
        @endif
    </nav>
@endif
