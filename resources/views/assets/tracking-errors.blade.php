@if($formErrors->any())
    <ul class="action-note !mt-0 !grid gap-1 text-[var(--error)]" role="alert">
        @foreach($formErrors->all() as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
