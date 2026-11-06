<div class="grid gap-4 sm:grid-cols-2">
    @foreach(['status_id' => [$statuses, $statusId], 'condition_id' => [$conditions, $conditionId]] as $key => [$options, $selected])
        <x-ui.select-field :name="$key" :label="__('assets.fields.'.$key)" required>
            @foreach($options as $option)
                <option value="{{ $option->id }}" @selected($value($key, $selected) === (string) $option->id)>{{ $option->name }}</option>
            @endforeach
        </x-ui.select-field>
    @endforeach
</div>
