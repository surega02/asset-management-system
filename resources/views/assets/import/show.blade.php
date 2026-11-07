@php
    $isPreview = $record->status === 'preview';
    $finished = $record->status === 'finished';
    $done = $counts->only(['succeeded', 'failed', 'skipped'])->sum();
    $decisionForm = 'import-decisions';
@endphp
<x-layouts.account :title="__('asset_import.batch', ['id' => $record->id])">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a class="nav-link" href="{{ route('assets.import.create') }}">{{ __('asset_import.back') }}</a>
        <p class="font-semibold">{{ __('asset_import.'.$record->status) }}</p>
    </div>
    @if($isPreview)
        <p class="max-w-prose">{{ __('asset_import.preview_help') }}</p>
        <form id="{{ $decisionForm }}" action="{{ route('assets.import.decide', $record->id) }}" method="post"
            class="flex flex-wrap items-center gap-2">
            @csrf
            <input type="hidden" name="page" value="{{ $rows->currentPage() }}">
            <x-ui.button name="intent" value="save" type="submit">{{ __('asset_import.save_choices') }}</x-ui.button>
            <x-ui.button name="intent" value="confirm" variant="primary" type="submit">
                {{ __('asset_import.confirm', ['count' => $record->total_rows]) }}
            </x-ui.button>
        </form>
    @else
        <div class="grid gap-3">
            <p>{{ __('asset_import.progress', ['done' => $done, 'total' => $record->total_rows]) }}</p>
            <progress class="w-full max-w-lg" aria-label="{{ __('asset_import.progress_label') }}" value="{{ $done }}" max="{{ $record->total_rows }}"></progress>
            <dl class="flex flex-wrap gap-x-6 gap-y-1 tabular-nums">
                @foreach(['succeeded', 'failed', 'skipped', 'pending', 'processing'] as $status)
                    <div class="flex items-baseline gap-1.5">
                        <dt>{{ __('asset_import.'.$status) }}</dt>
                        <dd class="font-semibold">{{ $counts[$status] ?? 0 }}</dd>
                    </div>
                @endforeach
            </dl>
            <div class="flex flex-wrap gap-2">
                @if(!$finished)
                    <a class="ui-button" href="{{ $rows->url($rows->currentPage()) }}">{{ __('asset_import.refresh') }}</a>
                @endif
                @if(($counts['failed'] ?? 0) > 0)
                    <a class="ui-button" href="{{ route('assets.import.errors', $record->id) }}">{{ __('asset_import.download_errors') }}</a>
                @endif
            </div>
        </div>
    @endif
    <x-ui.panel>
        <ul class="divide-y divide-[var(--border)]" aria-label="{{ __('asset_import.rows') }}">
            @foreach($rows as $row)
                @php
                    $description = $preview->get($row->id);
                    $payload = $row->payload ?? [];
                    $label = filled($payload['name'] ?? null) ? $payload['name'] : (($payload['asset_code'] ?? '') ?: __('asset_import.unnamed'));
                @endphp
                <li class="grid gap-3 p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <h2 class="wrap-anywhere">{{ __('asset_import.line', ['line' => $row->row_number]) }} · {{ $label }}</h2>
                            @if(filled($payload['asset_code'] ?? null))
                                <p>{{ __('qr.code') }} <span class="font-mono wrap-anywhere">{{ $payload['asset_code'] }}</span></p>
                            @endif
                        </div>
                        @if($description)
                            <div class="grid gap-1">
                                <label for="choice-{{ $row->id }}">{{ __('asset_import.line_action', ['line' => $row->row_number]) }}</label>
                                <select class="ui-select" id="choice-{{ $row->id }}" name="choices[{{ $row->id }}]" form="{{ $decisionForm }}">
                                    @if($description['candidate'] !== 'skip')
                                        <option value="{{ $description['candidate'] }}" @selected($row->action === $description['candidate'])>
                                            {{ __('asset_import.action_'.$description['candidate']) }}
                                        </option>
                                    @endif
                                    <option value="skip" @selected($row->action === 'skip')>{{ __('asset_import.action_skip') }}</option>
                                </select>
                            </div>
                        @else
                            <p class="font-semibold">{{ __('asset_import.'.$row->status) }} · {{ __('asset_import.action_'.$row->action) }}</p>
                        @endif
                    </div>
                    @if(($description['similar'] ?? false) || $row->possible_duplicate)
                        <p class="rounded-md bg-[var(--warning)] px-3 py-2 text-[var(--warning-ink)]">{{ __('asset_import.similar') }}</p>
                    @endif
                    @if($description)
                        @if($description['messages'] !== [])
                            <ul class="grid list-disc gap-1 ps-5 text-[var(--error)]">
                                @foreach($description['messages'] as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @elseif(isset($visible[$row->asset_id]))
                        <p><a class="font-semibold underline underline-offset-4" href="{{ route('assets.show', $row->asset_id) }}">{{ $visible[$row->asset_id] }}</a></p>
                    @endif
                    @if($row->errors)
                        <p class="text-[var(--error)]">
                            {{ __('asset_import.'.$row->errors['key']) }}
                            @if(!empty($row->errors['fields']))
                                <span class="font-mono wrap-anywhere">{{ implode(', ', $row->errors['fields']) }}</span>
                            @endif
                        </p>
                    @endif
                    @if($payload !== [])
                        <details class="grid gap-2">
                            <summary class="w-fit cursor-pointer font-semibold">{{ __('asset_import.values') }}</summary>
                            <dl class="mt-2 grid gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach(array_filter($payload, fn ($value) => $value !== '') as $key => $value)
                                    <div class="min-w-0">
                                        <dt class="font-semibold wrap-anywhere">{{ __('assets.fields.'.$key) === 'assets.fields.'.$key ? $key : __('assets.fields.'.$key) }}</dt>
                                        <dd class="wrap-anywhere">{{ $value }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                    @if($row->attempt_count > 1)
                        <details>
                            <summary class="w-fit cursor-pointer font-semibold">{{ __('asset_import.attempt_history') }}</summary>
                            <ol class="mt-2 grid gap-1">
                                @foreach($attempts->get($row->id, []) as $attempt)
                                    <li>{{ __('asset_import.attempt_line', ['number' => $attempt->attempt_number, 'outcome' => __('asset_import.'.($attempt->outcome === 'interrupted' ? 'attempt_interrupted' : $attempt->outcome))]) }}
                                        · <time datetime="{{ \Illuminate\Support\Carbon::parse($attempt->attempted_at)->toAtomString() }}">{{ $attempt->attempted_at }}</time>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endif
                    @if($finished && $row->status === 'failed')
                        <details>
                            <summary class="w-fit cursor-pointer font-semibold">{{ __('asset_import.correction') }}</summary>
                            <form method="post" action="{{ route('assets.import.retry', [$record->id, $row->id]) }}" class="mt-3 grid gap-3">
                                @csrf
                                <input type="hidden" name="attempt" value="{{ $row->attempt_count }}">
                                <p>{{ __('asset_import.retry_help', ['attempt' => $row->attempt_count]) }}</p>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach(array_diff(array_unique([...\App\Actions\Imports\AssetImportSheet::COLUMNS, ...array_keys($payload)]), ['asset_code']) as $key)
                                        <x-ui.field :id="'retry-'.$row->id.'-'.$loop->index" :name="'values['.$key.']'" :label="$key" :value="$payload[$key] ?? ''" maxlength="2000" />
                                    @endforeach
                                </div>
                                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('asset_import.retry') }}</x-ui.button>
                            </form>
                        </details>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.panel>
    @include('people.pagination', ['pages' => $rows])
</x-layouts.account>
