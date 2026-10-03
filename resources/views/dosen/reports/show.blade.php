<x-layouts.app :title="'Detail Jawaban · '.$attempt->user->nim_nidn">
    @php
        $F = \App\Support\Format::class;
        $hasil = $attempt->result;
    @endphp

    <a href="{{ route('dosen.reports.index', $exam) }}" class="btn btn-ghost btn-sm mb-4"><x-icon name="chevron-left" class="size-4" />Rekap nilai</a>

    <section class="card card-important mb-6" aria-labelledby="judul-peserta">
        <h2 id="judul-peserta" class="font-display text-h2 font-semibold text-maroon-900">{{ $attempt->user->nama }}</h2>
        <p class="text-small text-stone-500">{{ $attempt->user->nim_nidn }} · {{ $exam->judul }}</p>
        <dl class="mt-4 grid gap-4 text-small sm:grid-cols-3 lg:grid-cols-6">
            <div><dt class="text-stone-500">Status</dt><dd class="font-medium text-ink">{{ $attempt->status->label() }}{{ $attempt->alasan_selesai ? ' · '.$attempt->alasan_selesai->label() : '' }}</dd></div>
            <div><dt class="text-stone-500">Dikirim</dt><dd class="font-medium text-ink">{{ $attempt->selesai?->translatedFormat('d M Y H:i') ?? '–' }}</dd></div>
            <div><dt class="text-stone-500">Skor PG</dt><dd class="font-medium text-ink">{{ $F::angka($hasil?->skor_pg) }}</dd></div>
            <div><dt class="text-stone-500">Skor esai final</dt><dd class="font-medium text-ink">{{ $F::angka($hasil?->skor_esai_final) }}</dd></div>
            <div><dt class="text-stone-500">Nilai akhir</dt><dd class="font-medium text-ink">{{ $F::angka($hasil?->nilai_akhir) }}</dd></div>
            <div><dt class="text-stone-500">Pelanggaran</dt><dd class="font-medium text-ink">{{ $attempt->jumlah_pelanggaran }}/{{ $exam->batas_pelanggaran }}</dd></div>
        </dl>
    </section>

    <section aria-labelledby="judul-jawaban" class="mb-6">
        <h2 id="judul-jawaban" class="mb-4 text-h2 font-semibold">Jawaban (urutan tampil mahasiswa)</h2>
        @foreach ($soal as ['nomor' => $nomor, 'nomor_asli' => $nomorAsli, 'question' => $question, 'answer' => $answer])
            <article class="card mb-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-h3 font-semibold">No. {{ $nomor }} <span class="text-small font-normal text-stone-500">(soal asli {{ $nomorAsli }})</span></h3>
                    <span class="badge badge-neutral">Skor {{ $F::angka($answer?->skor_final) }} / {{ $F::angka($question->bobot) }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-stone-700">{{ $question->teks }}</p>

                @if ($question->isPg())
                    @php
                        $kunci = $question->options->firstWhere('is_correct', true);
                        $benar = $answer?->option_id !== null && $answer->option_id === $kunci?->id;
                    @endphp
                    <dl class="mt-3 grid gap-2 text-small sm:grid-cols-2">
                        <div>
                            <dt class="text-stone-500">Jawaban</dt>
                            <dd class="flex items-center gap-2 text-ink">
                                @if (! $answer?->option)
                                    <span class="badge badge-neutral">Kosong</span>
                                @else
                                    <span class="badge {{ $benar ? 'badge-success' : 'badge-danger' }}"><x-icon :name="$benar ? 'check' : 'x'" class="size-3.5" />{{ $benar ? 'Benar' : 'Salah' }}</span>
                                    {{ $answer->option->label }}. {{ $answer->option->teks }}
                                @endif
                            </dd>
                        </div>
                        <div><dt class="text-stone-500">Kunci</dt><dd class="text-ink">{{ $kunci?->label }}. {{ $kunci?->teks }}</dd></div>
                    </dl>
                @else
                    <div class="mt-3 rounded-md border border-stone-200 px-4 py-3">
                        <p class="label-caps text-stone-500">Jawaban esai</p>
                        <p class="mt-1 whitespace-pre-line text-ink">{{ filled($answer?->teks_jawaban) ? $answer->teks_jawaban : '(tidak dijawab)' }}</p>
                    </div>
                    <p class="mt-2 text-small text-stone-500">
                        Similarity {{ $answer?->similarity === null ? '–' : number_format($answer->similarity, 2, ',', '.') }} ·
                        rekomendasi {{ $F::angka($answer?->skor_sistem) }} ·
                        final {{ $F::angka($answer?->skor_final) }}
                        @if ($answer && filled($answer->teks_jawaban))
                            · <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $answer->id]) }}" class="font-semibold text-maroon-700 underline">koreksi</a>
                        @endif
                    </p>
                @endif
            </article>
        @endforeach
    </section>

    <section class="card overflow-hidden p-0" aria-labelledby="judul-log">
        <h2 id="judul-log" class="px-6 pt-6 text-h3 font-semibold">Log pelanggaran dan insiden</h2>
        @if ($attempt->logs->isEmpty())
            <p class="px-6 pb-6 pt-2 text-stone-500">Tidak ada catatan.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table-eg">
                    <thead><tr><th scope="col">Waktu</th><th scope="col">Jenis</th><th scope="col">Pemicu</th><th scope="col">Dihitung</th><th scope="col">Dimaafkan</th></tr></thead>
                    <tbody>
                        @foreach ($attempt->logs->sortBy('waktu') as $log)
                            <tr>
                                <td class="font-mono">{{ $log->waktu->format('H:i:s.v') }}</td>
                                <td>{{ $log->jenis->label() }}</td>
                                <td>{{ $log->detail['pemicu'] ?? '–' }}</td>
                                <td>{{ $log->dihitung ? 'Ya' : 'Tidak' }}</td>
                                <td>{{ $log->dimaafkan ? 'Ya' : 'Tidak' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
