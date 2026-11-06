@if($pages->hasPages())
    <nav aria-label="{{ __('masters.pagination') }}" class="flex flex-wrap gap-2">
        @if($pages->previousPageUrl())
            <a class="ui-button" rel="prev" href="{{ $pages->previousPageUrl() }}">{{ __('masters.previous') }}</a>
        @endif
        @if($pages->nextPageUrl())
            <a class="ui-button" rel="next" href="{{ $pages->nextPageUrl() }}">{{ __('masters.next') }}</a>
        @endif
    </nav>
@endif
