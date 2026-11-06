<x-layouts.account :title="__('people.batch', ['id' => $record->id])">
    <a class="nav-link justify-self-start" href="{{ route('employees.import.create') }}">{{ __('people.back_import') }}</a>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <dl class="flex flex-wrap gap-x-6 gap-y-2">
            @foreach(['pending', 'succeeded', 'failed'] as $status)
                <div><dt>{{ __('people.'.$status) }}</dt><dd class="font-semibold tabular-nums">{{ $counts[$status] ?? 0 }}</dd></div>
            @endforeach
        </dl>
        <div class="flex flex-wrap gap-2">
            @if($counts['pending'] ?? 0)
                <form method="post" action="{{ route('employees.import.process', $record->id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="primary">{{ __('people.process_rows') }}</x-ui.button>
                </form>
            @endif
            @if($canRetry)
                <form method="post" action="{{ route('employees.import.process', $record->id) }}">
                    @csrf
                    <input type="hidden" name="retry" value="1">
                    <x-ui.button type="submit">{{ __('people.retry_rows') }}</x-ui.button>
                </form>
            @endif
        </div>
    </div>
    @if($counts['failed'] ?? 0)
        <p>{{ __('people.correct_import') }}</p>
    @endif
    <x-ui.panel>
        <table class="w-full table-fixed text-sm">
            <caption class="sr-only">{{ __('people.batch', ['id' => $record->id]) }}</caption>
            <thead class="bg-[var(--subtle)]">
                <tr>
                    <th scope="col" class="w-20 p-3 text-start">{{ __('people.line') }}</th>
                    <th scope="col" class="p-3 text-start">{{ __('people.result') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr class="border-t border-[var(--border)] align-top">
                        <th scope="row" class="p-3 text-start tabular-nums">{{ $row->line }}</th>
                        <td class="wrap-anywhere p-3">
                            {{ __('people.'.$row->status) }}
                            @if($row->error_key)<span class="block text-[var(--error)]">{{ __('people.'.$row->error_key) }}</span>@endif
                            @if($row->attempts > 1)<span class="block text-[var(--muted)]">{{ __('people.attempts', ['count' => $row->attempts]) }}</span>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
