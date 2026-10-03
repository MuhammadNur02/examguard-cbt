{{-- Pratinjau sebagai mahasiswa (FR-02.7). Dirender dari payload yang sama dengan layar ujian, tanpa exam.js. --}}
<x-layouts.exam :title="'Pratinjau · '.$exam->judul" skrip="resources/js/app.js">
    <div class="flex min-h-screen flex-col">
        <div class="bg-maroon-900 text-white">
            <div class="mx-auto flex max-w-[960px] flex-wrap items-center justify-between gap-3 px-4 py-2.5 text-small">
                <p class="flex items-center gap-2"><x-icon name="eye" class="size-4" /><span><strong>Mode pratinjau.</strong> Tampilan sama dengan layar mahasiswa; jawaban tidak disimpan, tidak ada attempt atau nilai, dan pemantauan pelanggaran tidak aktif.</span></p>
                <div class="flex gap-2">
                    @if ($exam->acak_soal || $exam->acak_opsi)
                        <a href="{{ route('dosen.exams.preview', $exam) }}" class="btn btn-sm border border-white/40 text-white hover:bg-white/10"><x-icon name="refresh-cw" class="size-4" />Acak ulang</a>
                    @endif
                    <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-sm border border-white/40 text-white hover:bg-white/10"><x-icon name="chevron-left" class="size-4" />Kembali</a>
                </div>
            </div>
        </div>

        <header class="sticky top-0 z-30 border-b border-stone-200 bg-white">
            <div class="mx-auto flex h-14 max-w-[960px] items-center justify-between gap-4 px-4">
                <p class="truncate font-semibold text-ink">{{ $exam->judul }}</p>
                <div class="flex shrink-0 items-center gap-4">
                    <span class="badge badge-neutral">Pelanggaran 0/{{ $exam->batas_pelanggaran }}</span>
                    <div class="flex items-center gap-1.5 font-mono text-[20px] font-medium text-ink" aria-label="Durasi">
                        {{ (intdiv($exam->durasi_menit, 60) > 0 ? intdiv($exam->durasi_menit, 60).':' : '').sprintf('%02d:00', $exam->durasi_menit % 60) }}
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto grid w-full max-w-[960px] flex-1 items-start gap-6 px-4 py-6 lg:grid-cols-[1fr_13rem]">
            <section class="min-w-0 space-y-6" aria-label="Soal">
                <p class="text-small text-stone-500">
                    @if ($exam->acak_soal || $exam->acak_opsi)
                        Urutan untuk satu mahasiswa simulasi (seed {{ $seed }}). Mahasiswa lain mendapat urutan berbeda.
                    @else
                        Pengacakan dimatikan: semua mahasiswa mendapat urutan ini.
                    @endif
                    Di layar ujian, soal tampil satu per satu.
                </p>

                @foreach ($soal as $item)
                    <article id="soal-{{ $item['nomor'] }}" class="card scroll-mt-20" aria-labelledby="judul-soal-{{ $item['nomor'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 id="judul-soal-{{ $item['nomor'] }}" class="text-h3 font-semibold text-ink">
                                Soal {{ $item['nomor'] }} <span class="font-normal text-stone-500">dari {{ count($soal) }}</span>
                            </h2>
                            <span class="badge badge-neutral">Bobot {{ \App\Support\Format::angka($item['bobot']) }}</span>
                        </div>

                        <p class="mt-4 whitespace-pre-line text-question text-ink">{{ $item['teks'] }}</p>

                        @if ($item['tipe'] === 'pg')
                            <fieldset class="mt-6 space-y-3">
                                <legend class="sr-only">Pilihan jawaban</legend>
                                @foreach ($item['opsi'] as $opsi)
                                    <label class="group flex cursor-pointer items-start gap-3 rounded-md border border-stone-200 bg-white px-4 py-3 text-question text-ink transition duration-150 ease-out hover:bg-maroon-50 has-[:checked]:border-maroon-700 has-[:checked]:bg-maroon-100 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-maroon-500 has-[:focus-visible]:ring-offset-2">
                                        <input type="radio" name="opsi-{{ $item['nomor'] }}" class="sr-only">
                                        <span class="mt-1 flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-stone-500 group-has-[:checked]:border-maroon-700"><span class="size-2.5 rounded-full bg-maroon-700 opacity-0 group-has-[:checked]:opacity-100"></span></span>
                                        <span class="font-semibold">{{ $opsi['huruf'] }}.</span>
                                        <span class="flex-1">{{ $opsi['teks'] }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @else
                            <div class="mt-6">
                                <label for="esai-{{ $item['nomor'] }}" class="form-label">Jawaban Anda</label>
                                <textarea id="esai-{{ $item['nomor'] }}" class="form-input min-h-[160px] text-question" maxlength="20000"></textarea>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>

            <aside class="space-y-4 lg:sticky lg:top-20" aria-labelledby="judul-navigasi">
                <div class="card p-4">
                    <h2 id="judul-navigasi" class="label-caps text-stone-500">Nomor soal</h2>
                    <nav class="mt-3 grid grid-cols-5 gap-2" aria-label="Navigasi nomor soal">
                        @foreach ($soal as $item)
                            <a href="#soal-{{ $item['nomor'] }}" class="flex size-10 items-center justify-center rounded-sm border border-stone-200 bg-white text-small font-semibold text-ink hover:bg-maroon-50">{{ $item['nomor'] }}</a>
                        @endforeach
                    </nav>
                </div>
                <button type="button" class="btn btn-primary w-full" disabled title="Tidak tersedia di pratinjau"><x-icon name="send" class="size-4" />Kirim Jawaban</button>
            </aside>
        </main>
    </div>
</x-layouts.exam>
