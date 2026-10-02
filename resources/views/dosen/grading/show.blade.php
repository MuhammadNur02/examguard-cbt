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
        <div class="grid items-start gap-6 xl:grid-cols-[1fr_15rem]">
            <div class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <section class="card" aria-labelledby="judul-jawaban">
                        <h2 id="judul-jawaban" class="text-h3 font-semibold">Jawaban mahasiswa</h2>
                        <p class="text-small text-stone-500">{{ $dipilih->attempt->user->nim_nidn }} · {{ $dipilih->attempt->user->nama }}</p>
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
                                        @if ($dipilih->kata_kunci_cocok === null)
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
                <ul class="mt-2 max-h-[32rem] divide-y divide-stone-200 overflow-y-auto border-t border-stone-200">
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
