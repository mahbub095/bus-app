@php
/**
 * Custom pagination component.
 * Expects a $paginator variable (LengthAwarePaginator or Paginator).
 * Renders navigation with clean HTML and our CSS classes.
 */
@endphp

@if (isset($paginator) && $paginator->hasPages())
    @php

    // Retrieve page parameter name from paginator (defaults to 'page')
    $pageName = method_exists($paginator, 'getPageName') ? $paginator->getPageName() : 'page';
    // Preserve existing query parameters except the page number.
    $query = request()->except($pageName);
    // Get the base URL without query string.
    $baseUrl = request()->url();
    // Retrieve the fragment (hash) from the full URL, if present.
    $fullUrl = request()->fullUrl();
    $fragment = parse_url($fullUrl, PHP_URL_FRAGMENT);
    // Build pagination URLs, appending the fragment when it exists.
    $buildUrl = function($page) use ($baseUrl, $query, $fragment, $pageName) {
        $url = $baseUrl . '?' . http_build_query(array_merge($query, [$pageName => $page]));
        return $fragment ? $url . "#{$fragment}" : $url;
    };
@endphp
        @php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $range = 2; // number of pages to show on each side of current
    $start = max(1, $current - $range);
    $end = min($last, $current + $range);
@endphp
    
    <nav aria-label="Pagination" class="custom-pagination">
        <ul class="pagination-list">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link">‹</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $buildUrl($current - 1) }}" rel="prev">‹</a></li>
            @endif

            {{-- First page shortcut --}}
            @if ($start > 1)
                <li class="page-item"><a class="page-link" href="{{ $buildUrl(1) }}">1</a></li>
                @if ($start > 2)
                    <li class="page-item disabled"><span class="page-ellipsis">…</span></li>
                @endif
            @endif

            {{-- Page range --}}
            @for ($i = $start; $i <= $end; $i++)
                @if ($i == $current)
                    <li class="page-item active"><span class="page-link">{{ $i }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $buildUrl($i) }}">{{ $i }}</a></li>
                @endif
            @endfor

            {{-- Last page shortcut --}}
            @if ($end < $last)
                @if ($end < $last - 1)
                    <li class="page-item disabled"><span class="page-ellipsis">…</span></li>
                @endif
                <li class="page-item"><a class="page-link" href="{{ $buildUrl($last) }}">{{ $last }}</a></li>
            @endif

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $buildUrl($current + 1) }}" rel="next">›</a></li>
            @else
                <li class="page-item disabled"><span class="page-link">›</span></li>
            @endif
        </ul>
    </nav>
@endif
