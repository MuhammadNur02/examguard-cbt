@props(['title', 'icon' => 'circle-alert'])
<x-layouts.guest :title="$title">
    <div class="card w-full max-w-md text-center">
        <x-icon :name="$icon" class="mx-auto size-10 text-status-danger" />
        <h1 class="mt-4 font-display text-h1 font-semibold text-maroon-900">{{ $title }}</h1>
        <p class="mt-2 text-stone-700">{{ $slot }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-6">Kembali ke beranda</a>
    </div>
</x-layouts.guest>
