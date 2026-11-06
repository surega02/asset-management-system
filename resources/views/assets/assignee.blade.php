<x-ui.select-field name="assignee" :label="__('assets.assignee')" :help="__('assets.assignee_help')" required>
    <option value="">{{ __('people.choose') }}</option>
    @foreach($targets as $kind => $options)
        @if($options->isNotEmpty())
            <optgroup label="{{ __('assets.target_'.$kind) }}">
                @foreach($options as $target)
                    @php($key = $kind.':'.$target->id)
                    <option value="{{ $key }}" @selected($value('assignee') === $key)>{{ $target->name }}</option>
                @endforeach
            </optgroup>
        @endif
    @endforeach
</x-ui.select-field>
