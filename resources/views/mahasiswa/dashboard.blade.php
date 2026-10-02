<x-layouts.app title="Beranda">
    @php
        $lencana = [
            'akan_datang' => ['badge-info', 'clock', 'Akan datang'],
            'dibuka' => ['badge-success', 'circle-check', 'Dibuka'],
            'berlangsung' => ['badge-warning', 'timer', 'Sedang dikerjakan'],
            'selesai' => ['badge-neutral', 'check', 'Selesai'],
            'ditutup' => ['badge-neutral', 'circle-x', 'Ditutup'],
        ];
    @endphp

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <section class="card overflow-hidden p-0" aria-labelledby="daftar-ujian">
            <h2 id="daftar-ujian" class="px-6 pt-6 text-h3 font-semibold">Ujian</h2>
            @if ($daftar->isEmpty())
                <p class="px-6 pb-6 pt-2 text-stone-500">Belum ada ujian yang diterbitkan.</p>
            @else
                <ul class="mt-4 divide-y divide-stone-200 border-t border-stone-200">
                    @foreach ($daftar as ['exam' => $exam, 'keadaan' => $keadaan])
                        @php [$kelas, $ikon, $label] = $lencana[$keadaan]; @endphp
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div class="min-w-0">
                                <p class="font-semibold text-ink">{{ $exam->judul }}</p>
                                <p class="text-small text-stone-500">{{ $exam->mata_kuliah }} · {{ $exam->mulai->translatedFormat('d M Y, H:i') }}–{{ $exam->selesaiPada()->format('H:i') }} WIB · {{ $exam->durasi_menit }} menit</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="badge {{ $kelas }}"><x-icon :name="$ikon" class="size-3.5" />{{ $label }}</span>
                                @if (in_array($keadaan, ['dibuka', 'berlangsung', 'selesai'], true))
                                    <a href="{{ route('mahasiswa.exams.show', $exam) }}" @class(['btn btn-sm', 'btn-primary' => $keadaan !== 'selesai', 'btn-secondary' => $keadaan === 'selesai'])>
                                        {{ ['dibuka' => 'Masuk', 'berlangsung' => 'Lanjutkan', 'selesai' => 'Lihat'][$keadaan] }}
                                    </a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="card h-fit" aria-labelledby="profil">
            <h2 id="profil" class="text-h3 font-semibold">Profil</h2>
            <dl class="mt-4 grid grid-cols-[5rem_1fr] gap-y-2 text-small">
                <dt class="text-stone-500">Nama</dt>
                <dd class="font-medium text-ink">{{ $user->nama }}</dd>
                <dt class="text-stone-500">NIM</dt>
                <dd class="font-mono text-ink">{{ $user->nim_nidn }}</dd>
                <dt class="text-stone-500">Kelas</dt>
                <dd class="text-ink">{{ $user->kelas->pluck('nama')->join(', ') ?: '–' }}</dd>
            </dl>
        </section>
    </div>
</x-layouts.app>
