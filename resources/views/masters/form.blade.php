<x-layouts.account :title="$record?->name ?? __('masters.create_'.$type->value)">
    <a class="nav-link justify-self-start" href="{{ route('masters.index', ['type' => $type, 'status' => $record && !$record->is_active ? 'archived' : null]) }}">
        {{ __('masters.back', ['section' => __('masters.'.$type->value)]) }}
    </a>

    <x-ui.panel class="max-w-2xl p-4 sm:p-6">
        @if(!$canWrite)
            <dl class="grid gap-4">
                <div><dt class="font-semibold">{{ __('masters.code') }}</dt><dd class="wrap-anywhere">{{ $record->code }}</dd></div>
                @if($type->owner())
                    <div><dt class="font-semibold">{{ __('masters.owner_'.$type->owner()->value) }}</dt><dd class="wrap-anywhere">{{ $record->owner->name }}</dd></div>
                @endif
                @if($type === \App\Support\MasterType::Location)
                    <div><dt class="font-semibold">{{ __('masters.parent') }}</dt><dd class="wrap-anywhere">{{ $record->parent?->name ?? __('masters.no_parent') }}</dd></div>
                @endif
                <div><dt class="font-semibold">{{ __('masters.status') }}</dt><dd>{{ __('masters.'.($record->is_active ? 'active' : 'archived')) }}</dd></div>
            </dl>
        @elseif(!$record && $type->owner() && $owners->isEmpty())
            <p>{{ __('masters.no_owner') }}</p>
        @else
            <form method="post" action="{{ $record ? route('masters.update', [$type, $record]) : route('masters.store', $type) }}"
                class="grid gap-4" x-data="{ branch: @js((string) old('owner_id', $record?->branch_id)) }">
                @csrf
                @if($record)
                    @method('PUT')
                @endif

                <x-ui.field name="name" :label="__('masters.name')" :value="old('name', $record?->name)" required maxlength="255" />
                <x-ui.field name="master_code" :label="__('masters.code')" :value="old('master_code', $record?->code)" required maxlength="64" />

                @if($type->owner())
                    @if($record)
                        <dl>
                            <dt class="font-semibold">{{ __('masters.owner_'.$type->owner()->value) }}</dt>
                            <dd class="wrap-anywhere">{{ $record->owner->name }}</dd>
                        </dl>
                    @else
                        <div class="grid gap-1.5">
                            <label for="owner_id" class="font-semibold">{{ __('masters.owner_'.$type->owner()->value) }}</label>
                            <select id="owner_id" name="owner_id" class="ui-select w-full" required
                                @change="branch = $el.value; if ($refs.parent) $refs.parent.value = ''"
                                @error('owner_id') aria-invalid="true" aria-describedby="owner-error" @enderror>
                                <option value="">{{ __('masters.choose_owner') }}</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" @selected((string) old('owner_id') === (string) $owner->id)>
                                        {{ $owner->name }} ({{ $owner->code }}){{ $type === \App\Support\MasterType::Department ? ' · '.$owner->owner->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('owner_id') <p id="owner-error" class="text-sm text-[var(--error)]">{{ $message }}</p> @enderror
                        </div>
                    @endif
                @endif

                @if($type === \App\Support\MasterType::Location)
                    <div class="grid gap-1.5">
                        <label for="parent_id" class="font-semibold">{{ __('masters.parent') }}</label>
                        <select id="parent_id" name="parent_id" x-ref="parent" class="ui-select w-full"
                            aria-describedby="parent-help @error('parent_id') parent-error @enderror"
                            @error('parent_id') aria-invalid="true" @enderror>
                            <option value="">{{ __('masters.no_parent') }}</option>
                            @foreach($parents as $parent)
                                <option value="{{ $parent->id }}" data-branch="{{ $parent->branch_id }}"
                                    @if(!$record) :hidden="$el.dataset.branch !== branch" :disabled="$el.dataset.branch !== branch" @endif
                                    @selected((string) old('parent_id', $record?->parent_id) === (string) $parent->id)>
                                    {{ $parent->name }} ({{ $parent->code }})
                                </option>
                            @endforeach
                        </select>
                        <p id="parent-help" class="text-sm">{{ __('masters.parent_help') }}</p>
                        @error('parent_id') <p id="parent-error" class="text-sm text-[var(--error)]">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="grid gap-1.5">
                    <label for="is_active" class="font-semibold">{{ __('masters.status') }}</label>
                    <select id="is_active" name="is_active" class="ui-select w-full" required
                        aria-describedby="archive-help @error('is_active') status-error @enderror"
                        @error('is_active') aria-invalid="true" @enderror>
                        <option value="1" @selected((string) old('is_active', $record ? (int) $record->is_active : 1) === '1')>{{ __('masters.active') }}</option>
                        <option value="0" @selected((string) old('is_active', $record ? (int) $record->is_active : 1) === '0')>{{ __('masters.archived') }}</option>
                    </select>
                    <p id="archive-help" class="text-sm">{{ __('masters.archive_help') }}</p>
                    @error('is_active') <p id="status-error" class="text-sm text-[var(--error)]">{{ $message }}</p> @enderror
                </div>
                <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('masters.save') }}</x-ui.button>
            </form>
        @endif
    </x-ui.panel>
</x-layouts.account>
