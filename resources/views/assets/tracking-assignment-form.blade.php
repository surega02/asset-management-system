@php
    $value = static fn (string $key): string => $errors->assignmentChange->any() && is_scalar(old($key)) ? (string) old($key) : '';
@endphp
<details @if($errors->assignmentChange->any()) open @endif>
    <summary class="min-h-11 cursor-pointer py-2 font-semibold">{{ __('tracking.change_assignment') }}</summary>
    <form method="post" action="{{ route('assets.reassign', $asset) }}" class="mt-2 grid gap-4">
        @csrf
        <input type="hidden" name="assignment_id" value="{{ $asset->activeAssignment->id }}">
        @include('assets.tracking-errors', ['formErrors' => $errors->assignmentChange])
        @include('assets.assignee', ['assigneeBag' => 'assignmentChange'])
        @include('assets.tracking-reason', ['bag' => 'assignmentChange', 'id' => 'assignment-reason'])
        <x-ui.button type="submit" variant="primary" class="justify-self-start">{{ __('tracking.save_assignment') }}</x-ui.button>
    </form>
</details>
