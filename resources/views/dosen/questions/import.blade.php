<x-layouts.app title="Impor Soal">
    <p class="-mt-4 mb-6 text-small text-stone-500">
        <a href="{{ route('dosen.exams.show', $exam) }}" class="text-maroon-600 hover:underline">{{ $exam->judul }}</a>
    </p>

    @if (session('galat_impor'))
        <div class="alert alert-danger mb-6" role="alert">
            <x-icon name="circle-alert" class="mt-0.5 size-5" />
            <div>
                <p class="font-semibold">Impor dibatalkan. Tidak ada soal yang disimpan. Perbaiki baris berikut lalu unggah ulang:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach (session('galat_impor') as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('dosen.questions.import.store', $exam) }}" enctype="multipart/form-data" class="card space-y-5 self-start" novalidate>
            @csrf
            <div>
                <label for="berkas" class="form-label">Berkas soal (.xlsx atau .csv)</label>
                <input id="berkas" name="berkas" type="file" required
                    accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="block w-full text-small file:mr-4 file:rounded-md file:border-0 file:bg-maroon-100 file:px-4 file:py-2.5 file:font-semibold file:text-maroon-700"
                    @error('berkas') aria-invalid="true" aria-describedby="berkas-error" @enderror>
                @error('berkas')
                    <p id="berkas-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
                @enderror
            </div>
            <p class="text-small text-stone-500">Soal hasil impor ditambahkan setelah soal yang sudah ada. Periksa kembali di halaman ujian sebelum menerbitkan.</p>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary"><x-icon name="upload" class="size-4" />Validasi &amp; Impor</button>
                <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>

        <section class="card" aria-labelledby="format-soal">
            <h2 id="format-soal" class="text-h3 font-semibold">Format berkas</h2>
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-small">
                <li>Satu baris = satu soal. Baris pertama berisi judul kolom seperti di templat.</li>
                <li><code class="font-mono">tipe</code>: <em>pg</em> atau <em>esai</em>. <code class="font-mono">teks</code> wajib.</li>
                <li><code class="font-mono">bobot</code>: 0,5–100; bila kosong, PG = 1 dan esai = 10.</li>
                <li>Soal PG: <code class="font-mono">opsi_a</code> dan <code class="font-mono">opsi_b</code> wajib, <code class="font-mono">opsi_c</code>–<code class="font-mono">opsi_e</code> opsional dan diisi berurutan; <code class="font-mono">kunci</code> berisi satu huruf (A–E).</li>
                <li><code class="font-mono">opsi_tetap</code> (opsional): huruf opsi yang tidak ikut diacak, mis. <em>E</em> untuk “Semua jawaban salah”. Pisahkan dengan koma bila lebih dari satu.</li>
                <li>Soal esai: <code class="font-mono">kunci_esai</code> wajib; <code class="font-mono">kata_kunci</code> opsional, dipisah koma.</li>
                <li>Maksimal {{ $maksBaris }} soal per berkas. Bila ada satu baris salah, tidak ada yang disimpan dan nomor barisnya ditampilkan.</li>
            </ul>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('dosen.questions.import.template') }}" class="btn btn-secondary"><x-icon name="file-spreadsheet" class="size-4" />Unduh templat Excel</a>
                <a href="{{ route('dosen.questions.import.template', ['format' => 'csv']) }}" class="btn btn-ghost"><x-icon name="download" class="size-4" />Templat CSV</a>
            </div>
        </section>
    </div>
</x-layouts.app>
