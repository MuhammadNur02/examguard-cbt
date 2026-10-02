<x-layouts.app :title="$exam->judul">
    <div class="mx-auto max-w-3xl space-y-6">
        <section class="card card-important" aria-labelledby="judul-ujian">
            <p class="label-caps text-stone-500">{{ $exam->mata_kuliah }}</p>
            <h2 id="judul-ujian" class="mt-1 font-display text-h1 font-semibold text-maroon-900">{{ $exam->judul }}</h2>
            <dl class="mt-5 grid gap-x-8 gap-y-3 text-small sm:grid-cols-3">
                <div><dt class="text-stone-500">Jadwal</dt><dd class="font-medium text-ink">{{ $exam->mulai->translatedFormat('d M Y, H:i') }} – {{ $exam->selesaiPada()->format('H:i') }} WIB</dd></div>
                <div><dt class="text-stone-500">Durasi</dt><dd class="font-medium text-ink">{{ $exam->durasi_menit }} menit</dd></div>
                <div><dt class="text-stone-500">Batas pelanggaran</dt><dd class="font-medium text-ink">{{ $exam->batas_pelanggaran }} kali</dd></div>
            </dl>
        </section>

        @switch($keadaan)
            @case('akan_datang')
                <div class="alert alert-info" role="status">
                    <x-icon name="clock" class="mt-0.5 size-5" />
                    <p>Ujian belum dibuka. Silakan kembali pada {{ $exam->mulai->translatedFormat('l, d M Y H:i') }} WIB.</p>
                </div>
            @break

            @case('ditutup')
                <div class="alert alert-warning" role="status">
                    <x-icon name="circle-x" class="mt-0.5 size-5" />
                    <p>Jadwal ujian sudah berakhir dan Anda tidak memulai ujian ini.</p>
                </div>
            @break

            @case('selesai')
                <section class="card" aria-labelledby="judul-terkirim">
                    <div class="flex items-start gap-3">
                        <x-icon name="circle-check" class="mt-1 size-6 text-status-success" />
                        <div>
                            <h2 id="judul-terkirim" class="text-h3 font-semibold">Jawaban terkirim</h2>
                            <p class="mt-1 text-stone-700">{{ $pesanSelesai }}</p>
                            <p class="mt-1 text-small text-stone-500">Dikirim {{ $attempt->selesai?->translatedFormat('d M Y, H:i') }} WIB.</p>
                            <p class="mt-3 text-small text-stone-700">Nilai dapat dilihat di <a href="{{ route('mahasiswa.grades') }}" class="font-semibold text-maroon-700 underline">Riwayat Nilai</a> setelah dipublikasikan dosen.</p>
                        </div>
                    </div>
                </section>
            @break

            @case('berlangsung')
                <section class="card" aria-labelledby="judul-lanjut">
                    <h2 id="judul-lanjut" class="text-h3 font-semibold">Ujian sedang berlangsung</h2>
                    <p class="mt-1 text-stone-700">Lanjutkan pengerjaan. Urutan soal dan jawaban tersimpan Anda tetap sama. Halaman ujian akan meminta layar penuh lagi.</p>
                    <a href="{{ route('mahasiswa.exams.work', $exam) }}" class="btn btn-primary mt-5"><x-icon name="maximize" class="size-4" />Lanjutkan Ujian</a>
                </section>
            @break

            @default
                <form method="POST" action="{{ route('mahasiswa.attempts.start', $exam) }}" class="card" aria-labelledby="judul-integritas" data-persetujuan>
                    @csrf
                    <div class="flex items-start gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-md bg-maroon-100 text-maroon-700">
                            <x-icon name="shield-check" class="size-6" />
                        </span>
                        <div>
                            <h2 id="judul-integritas" class="text-h3 font-semibold">Persetujuan Integritas</h2>
                            <p class="mt-1 text-stone-700">Selama ujian, sistem memantau dan mencatat:</p>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-2 text-small text-stone-700">
                        <li class="flex gap-2"><x-icon name="maximize" class="size-4 text-maroon-500" />Mode layar penuh. Keluar dari layar penuh (mis. menekan Esc) dicatat sebagai pelanggaran.</li>
                        <li class="flex gap-2"><x-icon name="monitor" class="size-4 text-maroon-500" />Perpindahan tab atau jendela, termasuk memuat ulang atau meninggalkan halaman ujian.</li>
                        <li class="flex gap-2"><x-icon name="copy" class="size-4 text-maroon-500" />Salin, tempel, klik kanan, dan seleksi teks dinonaktifkan di halaman ujian.</li>
                        <li class="flex gap-2"><x-icon name="activity" class="size-4 text-maroon-500" />Alamat IP dan jenis perangkat/peramban yang Anda gunakan.</li>
                    </ul>
                    <p class="mt-4 rounded-md bg-status-warning-bg px-4 py-3 text-small text-status-warning">
                        Peringatan ditampilkan untuk pelanggaran ke-1 sampai ke-{{ $exam->batas_pelanggaran }}. Pelanggaran ke-{{ $exam->batas_pelanggaran + 1 }} mengunci ujian dan mengirim jawaban yang sudah tersimpan secara otomatis. Catatan pelanggaran ditinjau dosen.
                    </p>
                    <label class="mt-5 flex items-start gap-3 text-ink">
                        <input type="checkbox" name="setuju" value="1" class="mt-1 size-5 accent-maroon-700" required data-persetujuan-centang>
                        <span>Saya memahami pemantauan di atas dan akan mengerjakan ujian ini secara jujur tanpa bantuan pihak atau sumber lain.</span>
                    </label>
                    <x-field-error name="setuju" />
                    <button type="submit" class="btn btn-primary mt-5" disabled data-persetujuan-tombol>
                        <x-icon name="shield-check" class="size-4" />Mulai Ujian
                    </button>
                </form>
        @endswitch
    </div>
</x-layouts.app>
