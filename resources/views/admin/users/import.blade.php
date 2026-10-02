<x-layouts.app title="Impor Akun dari CSV">
    @if (session('galat_impor'))
        <div class="alert alert-danger mb-6" role="alert">
            <x-icon name="circle-alert" class="mt-0.5 size-5" />
            <div>
                <p class="font-semibold">Impor dibatalkan. Tidak ada akun yang disimpan. Perbaiki baris berikut lalu unggah ulang:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach (session('galat_impor') as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('admin.users.import.store') }}" enctype="multipart/form-data" class="card space-y-5" novalidate>
            @csrf
            <div>
                <label for="berkas" class="form-label">Berkas CSV</label>
                <input id="berkas" name="berkas" type="file" accept=".csv,text/csv" required
                    class="block w-full text-small file:mr-4 file:rounded-md file:border-0 file:bg-maroon-100 file:px-4 file:py-2.5 file:font-semibold file:text-maroon-700"
                    @error('berkas') aria-invalid="true" aria-describedby="berkas-error" @enderror>
                @error('berkas')
                    <p id="berkas-error" class="form-error"><x-icon name="circle-alert" class="size-4" />{{ $message }}</p>
                @enderror
            </div>
            <div class="flex gap-3">
                <button type="submit" class="btn btn-primary"><x-icon name="upload" class="size-4" />Validasi &amp; Impor</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
            </div>
        </form>

        <section class="card" aria-labelledby="format-csv">
            <h2 id="format-csv" class="text-h3 font-semibold">Format berkas</h2>
            <ul class="mt-3 list-disc space-y-1.5 pl-5 text-small">
                <li>Baris pertama berisi judul kolom: <code class="font-mono">nim_nidn</code>, <code class="font-mono">nama</code>, <code class="font-mono">peran</code>, <code class="font-mono">kata_sandi</code>.</li>
                <li><code class="font-mono">nim_nidn</code> dan <code class="font-mono">nama</code> wajib. <code class="font-mono">peran</code> berisi <em>mahasiswa</em> (bawaan bila kosong) atau <em>dosen</em>.</li>
                <li><code class="font-mono">kata_sandi</code> opsional (minimal 8 karakter); bila kosong, sistem membuat kata sandi acak yang ditampilkan sekali setelah impor.</li>
                <li>Pemisah koma atau titik koma diterima (CSV dari Excel berlokal Indonesia memakai titik koma).</li>
                <li>Maksimal {{ $maksBaris }} baris per berkas. Bila ada satu baris salah, tidak ada yang disimpan.</li>
            </ul>
            <a href="{{ route('admin.users.import.template') }}" class="btn btn-secondary mt-5"><x-icon name="download" class="size-4" />Unduh templat CSV</a>
        </section>
    </div>
</x-layouts.app>
