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

    @php
        $berlangsung = $attempt->isBerlangsung();
        $adaPelanggaran = $attempt->logs->contains(fn ($log) => $log->dihitung && ! $log->dimaafkan);
    @endphp
    <section class="card mb-6" aria-labelledby="judul-kelola">
        <h2 id="judul-kelola" class="text-h3 font-semibold">Kelola peserta</h2>
        <p class="mt-1 text-small text-stone-500">
            @if ($berlangsung)
                Sedang mengerjakan · batas waktu {{ $attempt->batasWaktu()->format('H:i') }} WIB{{ $attempt->waktu_tambahan ? ' (termasuk tambahan '.$attempt->waktu_tambahan.' menit)' : '' }}.
            @endif
            Setiap tindakan wajib diberi alasan dan tercatat di log audit.
        </p>
        <div class="mt-4 grid gap-6 lg:grid-cols-2">
            @if ($adaPelanggaran)
                <form method="POST" action="{{ route('dosen.attempts.reset', [$exam, $attempt]) }}" class="space-y-3" data-confirm="Maafkan semua pelanggaran peserta ini?">
                    @csrf
                    <h3 class="font-semibold text-ink">Reset pelanggaran</h3>
                    <div>
                        <label for="alasan-reset" class="form-label">Alasan</label>
                        <input id="alasan-reset" name="alasan" type="text" class="form-input" required minlength="5" maxlength="500" placeholder="mis. gangguan perangkat yang dikonfirmasi pengawas">
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="refresh-cw" class="size-4" />Maafkan semua pelanggaran</button>
                    <p class="text-small text-stone-500">Atau maafkan satu per satu di <a href="#judul-log" class="font-semibold text-maroon-700 underline">log pelanggaran</a>.</p>
                </form>
            @endif

            @if ($berlangsung)
                <form method="POST" action="{{ route('dosen.attempts.extend', [$exam, $attempt]) }}" class="space-y-3">
                    @csrf
                    <h3 class="font-semibold text-ink">Tambah waktu</h3>
                    <div class="flex flex-wrap gap-3">
                        <div class="w-28">
                            <label for="menit-tambah" class="form-label">Menit</label>
                            <input id="menit-tambah" name="menit" type="number" min="1" max="180" value="10" class="form-input" required>
                        </div>
                        <div class="min-w-48 flex-1">
                            <label for="alasan-tambah" class="form-label">Alasan</label>
                            <input id="alasan-tambah" name="alasan" type="text" class="form-input" required minlength="5" maxlength="500">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="clock" class="size-4" />Tambah waktu</button>
                </form>

                <form method="POST" action="{{ route('dosen.attempts.lock', [$exam, $attempt]) }}" class="space-y-3 lg:col-span-2" data-confirm="Kunci ujian mahasiswa ini sekarang? Jawaban yang tersimpan langsung dikirim.">
                    @csrf
                    <h3 class="font-semibold text-status-danger">Kunci ujian</h3>
                    <p class="text-small text-stone-500">Mengakhiri ujian mahasiswa ini sekarang dan mengirim jawaban yang sudah tersimpan. Layar mahasiswa membeku pada heartbeat berikutnya (±{{ config('examguard.heartbeat_detik') }} detik). Dapat dibuka ulang bila keliru.</p>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-48 flex-1">
                            <label for="alasan-kunci" class="form-label">Alasan</label>
                            <input id="alasan-kunci" name="alasan" type="text" class="form-input" required minlength="5" maxlength="500">
                        </div>
                        <button type="submit" class="btn btn-sm border border-status-danger text-status-danger hover:bg-status-danger-bg"><x-icon name="lock" class="size-4" />Kunci &amp; kirim jawaban</button>
                    </div>
                </form>
            @else
                <form method="POST" action="{{ route('dosen.attempts.reopen', [$exam, $attempt]) }}" class="space-y-3" data-confirm="Buka ulang attempt ini? Mahasiswa dapat mengubah jawabannya kembali.">
                    @csrf
                    <h3 class="font-semibold text-ink">Buka ulang attempt</h3>
                    <p class="text-small text-stone-500">Mahasiswa mendapat sedikitnya waktu ini sejak dibuka ulang, juga bila jadwal ujian sudah berakhir. Pelanggaran yang melebihi batas harus dimaafkan dulu.</p>
                    <div class="flex flex-wrap gap-3">
                        <div class="w-28">
                            <label for="menit-buka" class="form-label">Menit</label>
                            <input id="menit-buka" name="menit" type="number" min="1" max="180" value="15" class="form-input" required>
                        </div>
                        <div class="min-w-48 flex-1">
                            <label for="alasan-buka" class="form-label">Alasan</label>
                            <input id="alasan-buka" name="alasan" type="text" class="form-input" required minlength="5" maxlength="500">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="lock-open" class="size-4" />Buka ulang</button>
                </form>
            @endif
        </div>
        @if ($errors->any())
            <div class="mt-4"><x-field-error name="alasan" /><x-field-error name="menit" /></div>
        @endif
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
                    <thead><tr><th scope="col">Waktu</th><th scope="col">Jenis</th><th scope="col">Keterangan</th><th scope="col">Dihitung</th><th scope="col">Dimaafkan</th></tr></thead>
                    <tbody>
                        @foreach ($attempt->logs->sortBy('waktu') as $log)
                            <tr>
                                <td class="font-mono">{{ $log->waktu->format('H:i:s.v') }}</td>
                                <td>{{ $log->jenis->label() }}</td>
                                <td>
                                    @if ($log->jenis === \App\Enums\LogType::PerangkatBerganti)
                                        @if (($log->detail['ip_sebelumnya'] ?? null) !== ($log->detail['ip_baru'] ?? null))
                                            <span class="block">IP {{ $log->detail['ip_sebelumnya'] ?? '?' }} → {{ $log->detail['ip_baru'] ?? '?' }}</span>
                                        @endif
                                        @if (($log->detail['ua_sebelumnya'] ?? null) !== ($log->detail['ua_baru'] ?? null))
                                            <span class="block" title="{{ $log->detail['ua_baru'] ?? '' }}">Peramban {{ $log->detail['perangkat_sebelumnya'] ?? '?' }} → {{ $log->detail['perangkat_baru'] ?? '?' }}</span>
                                        @endif
                                    @else
                                        {{ $log->detail['pemicu'] ?? '–' }}
                                    @endif
                                </td>
                                <td>{{ $log->dihitung ? 'Ya' : 'Tidak' }}</td>
                                <td>
                                    @if ($log->dimaafkan)
                                        <span class="block">Dimaafkan {{ $log->pemaaf?->nama }}, {{ $log->dimaafkan_pada?->format('d/m H:i') }}</span>
                                        <span class="block text-stone-500">{{ $log->alasan }}</span>
                                    @elseif ($log->dihitung)
                                        <details>
                                            <summary class="cursor-pointer font-semibold text-maroon-700">Maafkan</summary>
                                            <form method="POST" action="{{ route('dosen.attempts.forgive', [$exam, $attempt, $log]) }}" class="mt-2 flex gap-2">
                                                @csrf
                                                <label for="alasan-log-{{ $log->id }}" class="sr-only">Alasan</label>
                                                <input id="alasan-log-{{ $log->id }}" name="alasan" type="text" class="form-input py-1.5" required minlength="5" maxlength="500" placeholder="Alasan">
                                                <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                                            </form>
                                        </details>
                                    @else
                                        –
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
