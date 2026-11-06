@if(session('status'))
    <p class="action-note !mt-0" role="status">{{ session('status') }}</p>
@endif
@if($errors->any())
    <ul class="action-note !mt-0 !grid gap-1" role="alert">
        @foreach($errors->all() as $message)
            <li style="color: var(--error)">{{ $message }}</li>
        @endforeach
    </ul>
@endif
