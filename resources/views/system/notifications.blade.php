<x-layouts.account :title="__('system.notifications')">
    <x-ui.panel>
        <ul class="divide-y divide-[var(--border)]">
            @forelse($inbox as $notice)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4 sm:p-5">
                    <div class="grid gap-1">
                        <h2>{{ __('system.notice.'.$notice->kind) }}</h2>
                        <p><time>{{ $notice->created_at }}</time> · {{ __($notice->read_at ? 'system.read' : 'system.unread') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a class="ui-button" href="{{ $notice->url }}">{{ __('system.open_source') }}</a>
                        @if(!$notice->read_at)
                            <form method="post" action="{{ route('system.notifications.read', $notice->id) }}">
                                @csrf
                                <x-ui.button type="submit">{{ __('system.mark_read') }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="p-4">{{ __('system.no_notifications') }}</li>
            @endforelse
        </ul>
    </x-ui.panel>
    @include('people.pagination', ['pages' => $inbox])
</x-layouts.account>
