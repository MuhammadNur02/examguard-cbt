@props(['data'])
<div class="alert alert-warning mb-6 flex-col sm:flex-row sm:items-center" role="status">
    <x-icon name="key-round" class="mt-0.5 size-5" />
    <div class="flex-1">
        <p class="font-semibold">Kata sandi untuk {{ $data['nama'] }} ({{ $data['nim_nidn'] }}):
            <span class="ml-1 rounded-sm bg-white px-2 py-0.5 font-mono text-body text-ink">{{ $data['kata_sandi'] }}</span>
        </p>
        <p>Catat dan sampaikan sekarang. Kata sandi ini tidak akan ditampilkan lagi.</p>
    </div>
</div>
