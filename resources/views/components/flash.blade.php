@if (session('status'))
    <div class="alert alert-success mb-6" role="status">
        <x-icon name="circle-check" class="mt-0.5 size-5" />
        <p>{{ session('status') }}</p>
    </div>
@endif
@if (session('error'))
    <div class="alert alert-danger mb-6" role="alert">
        <x-icon name="circle-alert" class="mt-0.5 size-5" />
        <p>{{ session('error') }}</p>
    </div>
@endif
