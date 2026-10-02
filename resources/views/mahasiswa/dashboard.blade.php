<x-layouts.app title="Beranda">
    <section aria-labelledby="profil" class="card max-w-xl">
        <h2 id="profil" class="text-h3 font-semibold">Profil</h2>
        <dl class="mt-4 grid grid-cols-[8rem_1fr] gap-y-2 text-small">
            <dt class="text-stone-500">Nama</dt>
            <dd class="font-medium text-ink">{{ $user->nama }}</dd>
            <dt class="text-stone-500">NIM</dt>
            <dd class="font-mono text-ink">{{ $user->nim_nidn }}</dd>
            <dt class="text-stone-500">Kelas</dt>
            <dd class="text-ink">{{ $user->kelas->pluck('nama')->join(', ') ?: '–' }}</dd>
        </dl>
    </section>
</x-layouts.app>
