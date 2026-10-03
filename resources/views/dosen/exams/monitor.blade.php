<x-layouts.app :title="'Live Monitor · '.$exam->judul">
    <script type="application/json" id="konfigurasi-monitor">@json($konfigurasi)</script>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-small text-stone-500">
            {{ $exam->mata_kuliah }} · {{ $exam->mulai->translatedFormat('d M Y, H:i') }}–{{ $exam->selesaiPada()->format('H:i') }} WIB · batas pelanggaran {{ $exam->batas_pelanggaran }}
        </p>
        <p class="flex items-center gap-2 text-small text-stone-500">
            <x-icon name="refresh-cw" class="size-4" />
            <span id="monitor-status">Diperbarui tiap {{ $konfigurasi['pollDetik'] }} detik</span> · <span id="monitor-waktu">–</span>
        </p>
    </div>

    <section aria-label="Ringkasan peserta" class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['aktif' => ['Aktif', 'activity'], 'offline' => ['Offline', 'wifi-off'], 'selesai' => ['Selesai', 'circle-check'], 'terkunci' => ['Terkunci', 'lock']] as $kunci => [$label, $ikon])
            <div class="card flex items-start justify-between gap-4">
                <div>
                    <p id="ringkasan-{{ $kunci }}" class="font-display text-display font-bold text-maroon-900">0</p>
                    <p class="mt-1 text-small text-stone-500">{{ $label }}</p>
                </div>
                <x-icon :name="$ikon" class="size-6 text-maroon-500" />
            </div>
        @endforeach
    </section>

    <div class="grid items-start gap-6 xl:grid-cols-[1fr_20rem]">
        <section class="card overflow-hidden p-0" aria-labelledby="judul-peserta">
            <h2 id="judul-peserta" class="px-6 pt-6 text-h3 font-semibold">Peserta</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="table-eg">
                    <thead>
                        <tr>
                            <th scope="col">NIM</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Pelanggaran</th>
                            <th scope="col" class="text-right">Terjawab</th>
                            <th scope="col">Aktivitas terakhir</th>
                            <th scope="col" class="text-right">Sisa waktu</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody id="monitor-peserta">
                        <tr id="monitor-kosong"><td colspan="8" class="text-stone-500">Belum ada peserta yang memulai ujian.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card" aria-labelledby="judul-pelanggaran">
            <h2 id="judul-pelanggaran" class="text-h3 font-semibold">Pelanggaran terbaru</h2>
            <p id="pelanggaran-kosong" class="mt-3 text-small text-stone-500">Belum ada pelanggaran.</p>
            <ul id="monitor-pelanggaran" class="mt-3 space-y-2" aria-live="polite"></ul>
        </section>
    </div>

    <template id="ikon-aktif"><x-icon name="activity" class="size-3.5" /></template>
    <template id="ikon-offline"><x-icon name="wifi-off" class="size-3.5" /></template>
    <template id="ikon-selesai"><x-icon name="circle-check" class="size-3.5" /></template>
    <template id="ikon-terkunci"><x-icon name="lock" class="size-3.5" /></template>
    <template id="ikon-peringatan"><x-icon name="triangle-alert" class="size-4" /></template>

    @vite('resources/js/monitor.js')
</x-layouts.app>
