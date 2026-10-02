<x-layouts.exam :title="$exam->judul">
    <script type="application/json" id="konfigurasi-ujian">@json($konfigurasi)</script>

    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-30 border-b border-stone-200 bg-white">
            <div class="mx-auto flex h-14 max-w-[960px] items-center justify-between gap-4 px-4">
                <p class="truncate font-semibold text-ink">{{ $exam->judul }}</p>
                <div class="flex shrink-0 items-center gap-4">
                    <span id="status-simpan" class="hidden items-center gap-1.5 text-small text-stone-500 md:flex" aria-live="polite"></span>
                    <span id="lencana-pelanggaran" class="badge badge-neutral">Pelanggaran 0/{{ $exam->batas_pelanggaran }}</span>
                    <div id="timer" role="timer" aria-label="Sisa waktu" class="flex items-center gap-1.5 font-mono text-[20px] font-medium text-ink">
                        <span id="timer-ikon" class="hidden"><x-icon name="clock" class="size-5" /></span>
                        <span id="timer-teks">--:--</span>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto grid w-full max-w-[960px] flex-1 items-start gap-6 px-4 py-6 lg:grid-cols-[1fr_13rem]">
            <section id="area-soal" class="min-w-0 select-none" aria-label="Soal">
                <div class="card text-stone-500">Memuat soal…</div>
            </section>

            <aside class="space-y-4" aria-labelledby="judul-navigasi">
                <div class="card p-4">
                    <h2 id="judul-navigasi" class="label-caps text-stone-500">Nomor soal</h2>
                    <nav id="navigasi" class="mt-3 grid grid-cols-5 gap-2" aria-label="Navigasi nomor soal"></nav>
                    <ul class="mt-4 space-y-1.5 text-label text-stone-500">
                        <li class="flex items-center gap-2"><span class="inline-block size-3.5 rounded-sm border border-stone-200 bg-white"></span>Belum dijawab</li>
                        <li class="flex items-center gap-2"><span class="inline-block size-3.5 rounded-sm bg-maroon-700"></span>Terjawab</li>
                        <li class="flex items-center gap-2"><span class="inline-block size-3.5 rounded-sm bg-gold-500"></span>Ragu-ragu</li>
                    </ul>
                </div>
                <button id="tombol-kirim" type="button" class="btn btn-primary w-full">
                    <x-icon name="send" class="size-4" />Kirim Jawaban
                </button>
            </aside>
        </main>
    </div>

    <div id="layar-mulai" class="fixed inset-0 z-40 flex items-center justify-center bg-ivory px-4">
        <div class="card card-important max-w-md text-center">
            <x-icon name="maximize" class="mx-auto size-10 text-maroon-700" />
            <h1 class="mt-4 font-display text-h2 font-semibold text-maroon-900">Siap mengerjakan</h1>
            <p class="mt-2 text-stone-700">Ujian berlangsung dalam mode layar penuh. Pemantauan pindah tab dan layar penuh aktif setelah Anda menekan tombol di bawah. Jangan memuat ulang halaman.</p>
            <p id="mulai-galat" class="form-error mt-4 hidden" role="alert"></p>
            <button id="tombol-mulai" type="button" class="btn btn-primary mt-6" disabled>
                <x-icon name="maximize" class="size-4" />Masuk Layar Penuh &amp; Mulai
            </button>
        </div>
    </div>

    <div id="modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-ink/60 px-4" role="alertdialog" aria-modal="true" aria-labelledby="modal-judul" aria-describedby="modal-isi">
        <div id="modal-kotak" class="modal-masuk w-full max-w-md rounded-lg border-t-4 border-t-status-warning bg-white p-6 shadow-pop">
            <div class="flex items-start gap-3">
                <span id="modal-ikon" class="mt-0.5 shrink-0"></span>
                <div>
                    <h2 id="modal-judul" class="text-h3 font-semibold text-ink"></h2>
                    <p id="modal-isi" class="mt-2 whitespace-pre-line text-stone-700"></p>
                </div>
            </div>
            <div id="modal-aksi" class="mt-6 flex flex-wrap justify-end gap-3"></div>
        </div>
    </div>

    <template id="ikon-peringatan"><x-icon name="triangle-alert" class="size-6 text-status-warning" /></template>
    <template id="ikon-bahaya"><x-icon name="shield-alert" class="size-6 text-status-danger" /></template>
    <template id="ikon-info"><x-icon name="info" class="size-6 text-status-info" /></template>
    <template id="ikon-sukses"><x-icon name="circle-check" class="size-6 text-status-success" /></template>
    <template id="ikon-tersimpan"><x-icon name="save" class="size-4" /></template>
    <template id="ikon-offline"><x-icon name="wifi-off" class="size-4" /></template>
</x-layouts.exam>
