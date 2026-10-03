<x-layouts.app :title="'Analisis Butir Soal · '.$exam->judul">
    @php
        $label = ['mudah' => ['Mudah', 'badge-success'], 'sedang' => ['Sedang', 'badge-info'], 'sukar' => ['Sukar', 'badge-danger']];
        $persen = fn (?float $p) => $p === null ? '–' : \App\Support\Format::angka($p).'%';
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-small text-stone-500">
            Dihitung dari attempt yang sudah selesai/terkunci. PG: persentase peserta yang menjawab benar (kosong dihitung salah).
            Esai: rata-rata skor final terhadap bobot dari jawaban yang sudah dikoreksi.
            Kategori: ≥ 70% mudah, 30–70% sedang, &lt; 30% sukar.
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('dosen.reports.index', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" />Rekap nilai</a>
            @if ($sulitDulu)
                <a href="{{ route('dosen.reports.analysis', $exam) }}" class="btn btn-secondary btn-sm">Urut nomor soal</a>
            @else
                <a href="{{ route('dosen.reports.analysis', [$exam, 'urut' => 'sulit']) }}" class="btn btn-secondary btn-sm">Urutkan: paling sering salah</a>
            @endif
        </div>
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @foreach ($label as $kunci => [$teks, $gaya])
            <div class="card p-4">
                <p class="text-small text-stone-500">Soal {{ strtolower($teks) }}</p>
                <p class="mt-1 font-display text-h1 font-semibold text-maroon-900">{{ $ringkasan[$kunci] ?? 0 }}</p>
            </div>
        @endforeach
    </div>

    <section class="card overflow-hidden p-0" aria-label="Analisis butir soal">
        <div class="overflow-x-auto">
            <table class="table-eg">
                <thead>
                    <tr>
                        <th scope="col">No.</th>
                        <th scope="col">Soal</th>
                        <th scope="col" class="text-right">Peserta</th>
                        <th scope="col">Benar / rata-rata</th>
                        <th scope="col">Kategori</th>
                        <th scope="col">Sebaran pilihan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($butir as $b)
                        @php $q = $b['question']; @endphp
                        <tr>
                            <td class="font-mono">{{ $b['nomor'] }}</td>
                            <td class="max-w-md">
                                <span class="badge badge-neutral mr-1">{{ $q->tipe->label() }}</span>
                                <span class="line-clamp-2 text-ink">{{ $q->teks }}</span>
                            </td>
                            <td class="num">{{ $b['peserta'] }}</td>
                            <td class="min-w-40">
                                <div class="flex items-center gap-2">
                                    <span class="w-14 font-semibold text-ink">{{ $persen($b['persen']) }}</span>
                                    <span class="h-2 flex-1 overflow-hidden rounded-full bg-stone-200" aria-hidden="true">
                                        <span class="block h-full bg-maroon-700" style="width: {{ (int) round($b['persen'] ?? 0) }}%"></span>
                                    </span>
                                </div>
                                <span class="text-small text-stone-500">
                                    @if ($q->isPg())
                                        {{ $b['benar'] }}/{{ $b['peserta'] }} benar
                                    @else
                                        {{ $b['dikoreksi'] }}/{{ $b['peserta'] }} dikoreksi
                                    @endif
                                </span>
                            </td>
                            <td>
                                @if ($b['kategori'])
                                    <span class="badge {{ $label[$b['kategori']][1] }}">{{ $label[$b['kategori']][0] }}</span>
                                    @if ($b['sementara'])
                                        <span class="block text-small text-stone-500">sementara</span>
                                    @endif
                                @else
                                    <span class="text-stone-500">–</span>
                                @endif
                            </td>
                            <td class="text-small">
                                @if ($q->isPg() && $b['peserta'] > 0)
                                    @foreach ($b['sebaran'] as $opsi => $jumlah)
                                        @php $kunci = $q->options->firstWhere('label', $opsi)?->is_correct; @endphp
                                        <span @class(['mr-2 whitespace-nowrap', 'font-semibold text-status-success' => $kunci, 'font-semibold text-status-danger' => ! $kunci && $opsi === $b['pengecoh_terkuat']])>
                                            {{ $opsi }}: {{ $jumlah }}@if ($kunci) ✓@endif
                                        </span>
                                    @endforeach
                                    @if ($b['kosong'] > 0)
                                        <span class="whitespace-nowrap text-stone-500">kosong: {{ $b['kosong'] }}</span>
                                    @endif
                                @else
                                    <span class="text-stone-500">–</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <p class="mt-3 text-small text-stone-500">✓ = kunci jawaban. Merah = pengecoh (opsi salah) yang paling sering dipilih; periksa kemungkinan kunci keliru atau soal ambigu bila pengecoh lebih sering dipilih daripada kunci.</p>
</x-layouts.app>
