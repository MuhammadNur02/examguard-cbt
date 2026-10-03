<x-layouts.app :title="'Koreksi Esai · '.$exam->judul">
    @php
        $F = \App\Support\Format::class;
        $kataKunci = $question->keywords ?? [];
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('dosen.grading.index', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" />Daftar soal esai</a>
        <p class="text-small text-stone-500">Bobot soal {{ $F::angka($question->bobot) }} · {{ $jawaban->whereNotNull('skor_final')->count() }}/{{ $jawaban->count() }} jawaban dikonfirmasi</p>
    </div>

    <section class="card mb-6" aria-labelledby="judul-soal">
        <h2 id="judul-soal" class="label-caps text-stone-500">Soal</h2>
        <p class="mt-2 whitespace-pre-line text-question text-ink">{{ $question->teks }}</p>
    </section>

    @if (! $dipilih)
        <p class="card text-stone-500">Belum ada jawaban dari attempt yang sudah selesai.</p>
    @else
        <section class="card mb-6" aria-labelledby="judul-cepat">
            <h2 id="judul-cepat" class="text-h3 font-semibold">Koreksi cepat</h2>
            <p class="mt-1 text-small text-stone-500">Terima skor rekomendasi sekaligus untuk jawaban yang <strong>belum dikonfirmasi</strong> dengan similarity di atas ambang. Skor yang sudah Anda tetapkan tidak berubah; jawaban lain tetap dikoreksi satu per satu.</p>
            <form method="POST" action="{{ route('dosen.grading.bulk', [$exam, $question]) }}" class="mt-4 flex flex-wrap items-end gap-4"
                data-confirm="Terima rekomendasi untuk semua jawaban yang memenuhi ambang? Skor dapat diubah lagi satu per satu.">
                @csrf
                <div>
                    <label for="ambang" class="form-label">Ambang similarity</label>
                    <select id="ambang" name="ambang" class="form-input w-auto">
                        @foreach ($opsiAmbang as $ambang => $jumlah)
                            <option value="{{ $ambang }}" @selected((float) $ambang === $ambangBawaan)>
                                ≥ {{ number_format((float) $ambang, 2, ',', '.') }} — {{ $kataKunci ? $jumlah['dengan_kata_kunci'] : $jumlah['tanpa_syarat'] }} jawaban{{ $kataKunci ? ' ('.$jumlah['tanpa_syarat'].' tanpa syarat kata kunci)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    <x-field-error name="ambang" />
                </div>
                @if ($kataKunci)
                    <label class="flex items-center gap-2 pb-3 text-small text-ink">
                        <input type="hidden" name="wajib_kata_kunci" value="0">
                        <input type="checkbox" name="wajib_kata_kunci" value="1" class="size-5 accent-maroon-700" checked>
                        Hanya bila semua kata kunci wajib terpenuhi
                    </label>
                @endif
                <button type="submit" class="btn btn-secondary"><x-icon name="check" class="size-4" />Terima massal</button>
            </form>
        </section>

        @if ($kemiripan->isNotEmpty())
            <section id="kemiripan" class="card mb-6" aria-labelledby="judul-kemiripan">
                <h2 id="judul-kemiripan" class="flex items-center gap-2 text-h3 font-semibold"><x-icon name="copy" class="size-5 text-status-warning" />Kemiripan antarmahasiswa</h2>
                <p class="mt-1 text-small text-stone-500">
                    Pasangan jawaban dengan kemiripan TF-IDF ≥ {{ number_format((float) config('examguard.ambang_kemiripan_esai'), 2, ',', '.') }}.
                    Ini penanda untuk ditinjau, bukan bukti kecurangan: jawaban yang sama-sama mendekati kunci juga bisa mirip.
                </p>
                <ul class="mt-3 divide-y divide-stone-200 text-small">
                    @foreach ($kemiripan as $p)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <span class="font-mono text-ink">{{ $p['a']->attempt->user->nim_nidn }} ↔ {{ $p['b']->attempt->user->nim_nidn }}</span>
                            <span class="flex items-center gap-3">
                                <span class="badge badge-warning">{{ number_format($p['skor'], 2, ',', '.') }}</span>
                                <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $p['a']->id]) }}" class="font-semibold text-maroon-700 underline">{{ $p['a']->attempt->user->nim_nidn }}</a>
                                <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $p['b']->id]) }}" class="font-semibold text-maroon-700 underline">{{ $p['b']->attempt->user->nim_nidn }}</a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="grid items-start gap-6 xl:grid-cols-[1fr_15rem]">
            <div class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="card" aria-labelledby="judul-jawaban">
                        <h2 id="judul-jawaban" class="text-h3 font-semibold">Jawaban mahasiswa</h2>
                        <p class="text-small text-stone-500">{{ $dipilih->attempt->user->nim_nidn }} · {{ $dipilih->attempt->user->nama }}</p>
                        @foreach ($kemiripan->filter(fn ($p) => $p['a']->id === $dipilih->id || $p['b']->id === $dipilih->id) as $p)
                            @php $lain = $p['a']->id === $dipilih->id ? $p['b'] : $p['a']; @endphp
                            <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $lain->id]) }}" class="badge badge-warning mr-1 mt-2">
                                <x-icon name="copy" class="size-3.5" />Mirip dengan {{ $lain->attempt->user->nim_nidn }} ({{ number_format($p['skor'], 2, ',', '.') }})
                            </a>
                        @endforeach
                        @if (filled($dipilih->teks_jawaban))
                            <p class="mt-4 whitespace-pre-line text-ink">{{ \App\Support\Highlight::kataKunci($dipilih->teks_jawaban, $kataKunci) }}</p>
                        @else
                            <p class="mt-4 italic text-stone-500">Tidak dijawab (skor otomatis 0).</p>
                        @endif
                    </section>

                    <section class="card" aria-labelledby="judul-kunci">
                        <h2 id="judul-kunci" class="text-h3 font-semibold">Kunci dosen</h2>
                        <p class="mt-4 whitespace-pre-line text-ink">{{ $question->kunci_esai }}</p>
                        @if ($kataKunci)
                            <h3 class="label-caps mt-5 text-stone-500">Kata kunci wajib</h3>
                            <ul class="mt-2 space-y-1 text-small">
                                @foreach ($kataKunci as $kata)
                                    @php $cocok = in_array($kata, $dipilih->kata_kunci_cocok ?? [], true); @endphp
                                    <li class="flex items-center gap-2">
                                        @if ($dipilih->kata_kunci_cocok === null && filled($dipilih->teks_jawaban))
                                            <x-icon name="circle-alert" class="size-4 text-stone-500" /><span>{{ $kata }} <span class="text-stone-500">(belum dicek)</span></span>
                                        @elseif ($cocok)
                                            <x-icon name="circle-check" class="size-4 text-status-success" /><span>{{ $kata }} <span class="sr-only">terpenuhi</span></span>
                                        @else
                                            <x-icon name="circle-x" class="size-4 text-status-danger" /><span>{{ $kata }} <span class="text-status-danger">tidak ditemukan</span></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>

                <section class="card card-important" aria-labelledby="judul-skor">
                    <h2 id="judul-skor" class="text-h3 font-semibold">Skor</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-small text-stone-500">Similarity (0,00–1,00)</dt>
                            <dd class="font-display text-h1 font-semibold text-maroon-900">{{ $dipilih->similarity === null ? '–' : number_format($dipilih->similarity, 2, ',', '.') }}</dd>
                        </div>
                        <div>
                            <dt class="text-small text-stone-500">Rekomendasi (similarity × bobot)</dt>
                            <dd class="font-display text-h1 font-semibold text-maroon-900">{{ $F::angka($dipilih->skor_sistem) }}</dd>
                            @if ($dipilih->similarity !== null)
                                <dd class="text-small text-stone-500">{{ number_format($dipilih->similarity, 4, ',', '.') }} × {{ $F::angka($question->bobot) }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-small text-stone-500">Skor final (keputusan dosen)</dt>
                            <dd class="font-display text-h1 font-semibold text-maroon-900">{{ $F::angka($dipilih->skor_final) }}</dd>
                            @if ($dipilih->dinilai_pada)
                                <dd class="text-small text-stone-500">Dikonfirmasi {{ $dipilih->dinilai_pada->translatedFormat('d M Y H:i') }}</dd>
                            @endif
                        </div>
                    </dl>

                    @if ($dipilih->skor_sistem === null)
                        <p class="alert alert-info mt-4"><x-icon name="info" class="mt-0.5 size-5" />Skor rekomendasi belum tersedia. Anda tetap dapat mengisi skor secara manual.</p>
                    @endif

                    <form method="POST" action="{{ route('dosen.grading.update', [$exam, $question, $dipilih]) }}" class="mt-6 flex flex-wrap items-end gap-3" novalidate>
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="skor" class="form-label">Skor (0–{{ $F::angka($question->bobot) }})</label>
                            <input id="skor" name="skor" type="number" min="0" max="{{ $question->bobot }}" step="0.01" class="form-input w-40"
                                value="{{ old('skor', $dipilih->skor_final ?? $dipilih->skor_sistem) }}"
                                @error('skor') aria-invalid="true" aria-describedby="skor-error" @enderror>
                        </div>
                        <button type="submit" name="aksi" value="setujui" class="btn btn-primary" @disabled($dipilih->skor_sistem === null)>
                            <x-icon name="check" class="size-4" />Setujui
                        </button>
                        <button type="submit" name="aksi" value="simpan" class="btn btn-secondary">
                            <x-icon name="save" class="size-4" />Simpan Perubahan
                        </button>
                        <div class="basis-full"><x-field-error name="skor" /></div>
                    </form>
                </section>

                <div class="flex justify-between gap-3">
                    @php
                        $sebelum = $posisi > 0 ? $jawaban[$posisi - 1] : null;
                        $sesudah = $jawaban[$posisi + 1] ?? null;
                    @endphp
                    @if ($sebelum)
                        <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $sebelum->id]) }}" class="btn btn-secondary btn-sm"><x-icon name="chevron-left" class="size-4" />Sebelumnya</a>
                    @else
                        <span></span>
                    @endif
                    @if ($sesudah)
                        <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $sesudah->id]) }}" class="btn btn-secondary btn-sm">Berikutnya<x-icon name="chevron-right" class="size-4" /></a>
                    @endif
                </div>
            </div>

            <aside class="card p-0" aria-labelledby="judul-daftar">
                <h2 id="judul-daftar" class="px-4 pt-4 label-caps text-stone-500">Jawaban ({{ $jawaban->count() }})</h2>
                <ul class="mt-2 max-h-128 divide-y divide-stone-200 overflow-y-auto border-t border-stone-200">
                    @foreach ($jawaban as $item)
                        <li>
                            <a href="{{ route('dosen.grading.show', [$exam, $question, 'jawaban' => $item->id]) }}"
                                @class(['flex items-center justify-between gap-2 px-4 py-2.5 text-small hover:bg-maroon-50', 'bg-maroon-100 font-semibold' => $item->id === $dipilih->id])
                                @if ($item->id === $dipilih->id) aria-current="true" @endif>
                                <span class="font-mono text-ink">{{ $item->attempt->user->nim_nidn }}</span>
                                @if ($item->skor_final !== null)
                                    <span class="flex items-center gap-1 text-status-success"><x-icon name="circle-check" class="size-4" />{{ $F::angka($item->skor_final) }}</span>
                                @else
                                    <span class="text-stone-500">belum</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </aside>
        </div>
    @endif
</x-layouts.app>
