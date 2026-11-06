<div class="grid gap-1.5">
    <label class="font-semibold" for="{{ $id }}">{{ __('people.fields.reason') }}</label>
    <textarea name="reason" id="{{ $id }}" class="ui-select w-full py-2" rows="2" maxlength="100" required
        @error('reason', $bag) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror>{{ $value('reason') }}</textarea>
    @error('reason', $bag)<p id="{{ $id }}-error" class="text-sm text-[var(--error)]">{{ $message }}</p>@enderror
</div>
