<x-layouts.print :title="'Laporan '.$exam->judul">
    @php $F = \App\Support\Format::class; @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('dosen.reports.index', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" />Rekap nilai</a>
        <div class="flex items-center gap-3">
            <p class="text-small text-stone-500">Pilih "Simpan sebagai PDF" pada dialog cetak untuk membuat berkas PDF.</p>
            <button type="button" class="btn btn-primary btn-sm" data-cetak><x-icon name="download" class="size-4" />Cetak / Simpan PDF</button>
        </div>
    </div>

    <header class="border-b-2 border-maroon-900 pb-3">
        <p class="label-caps text-stone-500">Laporan Hasil Ujian · ExamGuard CBT</p>
        <h1 class="mt-1 font-display text-h2 font-semibold text-maroon-900">{{ $exam->judul }}</h1>
        <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-1 text-small">
            <div><dt class="inline text-stone-500">Mata kuliah:</dt> <dd class="inline">{{ $exam->mata_kuliah }}</dd></div>
            <div><dt class="inline text-stone-500">Dosen:</dt> <dd class="inline">{{ $exam->dosen->nama }}</dd></div>
            <div><dt class="inline text-stone-500">Jadwal:</dt> <dd class="inline">{{ $exam->mulai->translatedFormat('d M Y H:i') }}–{{ $exam->selesaiPada()->format('H:i') }} WIB</dd></div>
            <div><dt class="inline text-stone-500">Dicetak:</dt> <dd class="inline">{{ now()->translatedFormat('d M Y H:i') }} WIB</dd></div>
        </dl>
    </header>

    <section class="mt-6" aria-labelledby="judul-rekap">
        <h2 id="judul-rekap" class="text-h3 font-semibold">Rekap nilai</h2>
        @if ($baris->isEmpty())
            <p class="mt-2 text-stone-500">Belum ada peserta yang mengerjakan ujian ini.</p>
        @else
            <table class="mt-2 w-full border-collapse text-small">
                <thead>
                    <tr class="border-b border-stone-500 text-left">
                        <th class="py-1 pr-2">No</th><th class="py-1 pr-2">NIM</th><th class="py-1 pr-2">Nama</th><th class="py-1 pr-2">Status</th>
                        <th class="py-1 pr-2 text-right">PG</th><th class="py-1 pr-2 text-right">Esai</th><th class="py-1 pr-2 text-right">Maks.</th>
                        <th class="py-1 pr-2 text-right">Nilai</th><th class="py-1 text-right">Pelanggaran</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($baris as $i => $b)
                        <tr class="break-inside-avoid border-b border-stone-200">
                            <td class="py-1 pr-2">{{ $i + 1 }}</td>
                            <td class="py-1 pr-2 font-mono">{{ $b['nim'] }}</td>
                            <td class="py-1 pr-2">{{ $b['nama'] }}</td>
                            <td class="py-1 pr-2">{{ $b['status'] }}{{ $b['attempt']->alasan_selesai ? ' · '.$b['attempt']->alasan_selesai->label() : '' }}</td>
                            <td class="py-1 pr-2 text-right">{{ $F::angka($b['skor_pg']) }}</td>
                            <td class="py-1 pr-2 text-right">{{ $F::angka($b['skor_esai']) }}</td>
                            <td class="py-1 pr-2 text-right">{{ $F::angka($b['skor_maksimal']) }}</td>
                            <td class="py-1 pr-2 text-right font-semibold">{{ $b['final'] ? $F::angka($b['nilai_akhir']) : $b['keterangan'] }}</td>
                            <td class="py-1 text-right">{{ $b['pelanggaran'] }}/{{ $exam->batas_pelanggaran }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-label text-stone-500">Nilai = (skor PG + skor esai final) / skor maksimal × 100. Pelanggaran = jumlah terhitung yang tidak dimaafkan.</p>
        @endif
    </section>

    @if ($baris->isNotEmpty())
        <section class="mt-8 break-before-page" aria-labelledby="judul-pelanggaran">
            <h2 id="judul-pelanggaran" class="text-h3 font-semibold">Laporan pelanggaran per mahasiswa</h2>
            <p class="text-label text-stone-500">Aplikasi web hanya mendeteksi dan mencatat; catatan ini penanda untuk ditinjau, bukan bukti mutlak.</p>

            @php $tanpaCatatan = $baris->filter(fn ($b) => ! $logs->has($b['attempt']->id))->pluck('nim'); @endphp
            @foreach ($baris as $b)
                @continue(! $logs->has($b['attempt']->id))
                <article class="mt-4 break-inside-avoid rounded-md border border-stone-200 p-3" data-blok-pelanggaran>
                    <h3 class="font-semibold">{{ $b['nim'] }} · {{ $b['nama'] }} <span class="font-normal text-stone-500">({{ $b['pelanggaran'] }}/{{ $exam->batas_pelanggaran }} terhitung)</span></h3>
                    <table class="mt-2 w-full border-collapse text-small">
                        <thead>
                            <tr class="border-b border-stone-300 text-left"><th class="py-1 pr-2">Waktu</th><th class="py-1 pr-2">Jenis</th><th class="py-1 pr-2">Keterangan</th><th class="py-1">Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($logs[$b['attempt']->id] as $log)
                                <tr class="border-b border-stone-200 align-top">
                                    <td class="py-1 pr-2 font-mono">{{ $log->waktu->format('H:i:s') }}</td>
                                    <td class="py-1 pr-2">{{ $log->jenis->label() }}</td>
                                    <td class="py-1 pr-2">
                                        @if ($log->jenis === \App\Enums\LogType::PerangkatBerganti)
                                            IP {{ $log->detail['ip_sebelumnya'] ?? '?' }} → {{ $log->detail['ip_baru'] ?? '?' }}
                                        @else
                                            {{ $log->detail['pemicu'] ?? '–' }}
                                        @endif
                                    </td>
                                    <td class="py-1">
                                        @if ($log->dimaafkan)
                                            Dimaafkan: {{ $log->alasan }} ({{ $log->pemaaf?->nama }})
                                        @elseif ($log->dihitung)
                                            Terhitung
                                        @else
                                            Dicatat, tidak dihitung
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            @endforeach

            @if ($tanpaCatatan->isNotEmpty())
                <p class="mt-4 text-small text-stone-500">Tidak ada pelanggaran: {{ $tanpaCatatan->join(', ') }}.</p>
            @endif
        </section>
    @endif
</x-layouts.print>
