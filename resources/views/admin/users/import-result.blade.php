<x-layouts.app title="Hasil Impor Akun">
    <div class="alert alert-success mb-6" role="status">
        <x-icon name="circle-check" class="mt-0.5 size-5" />
        <p>{{ count($kredensial) }} akun berhasil diimpor.</p>
    </div>

    @php $dibuatSistem = collect($kredensial)->whereNotNull('kata_sandi'); @endphp
    @if ($dibuatSistem->isNotEmpty())
        <div class="alert alert-warning mb-6" role="note">
            <x-icon name="triangle-alert" class="mt-0.5 size-5" />
            <p>Kata sandi di bawah dibuat sistem dan <strong>tidak akan ditampilkan lagi</strong>. Unduh atau catat sekarang sebelum meninggalkan halaman.</p>
        </div>
    @endif

    <section class="card overflow-hidden p-0" aria-label="Akun yang diimpor">
        <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6">
            <h2 class="text-h3 font-semibold">Akun baru</h2>
            @if ($dibuatSistem->isNotEmpty())
                <button type="button" class="btn btn-secondary btn-sm" data-unduh-tabel="tabel-kredensial" data-nama-berkas="kredensial-akun-baru.csv">
                    <x-icon name="download" class="size-4" />Unduh CSV kredensial
                </button>
            @endif
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="table-eg" id="tabel-kredensial">
                <thead>
                    <tr>
                        <th scope="col">nim_nidn</th>
                        <th scope="col">nama</th>
                        <th scope="col">peran</th>
                        <th scope="col">kata_sandi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kredensial as $baris)
                        <tr>
                            <td class="font-mono text-ink">{{ $baris['nim_nidn'] }}</td>
                            <td class="text-ink">{{ $baris['nama'] }}</td>
                            <td>{{ $baris['peran']->label() }}</td>
                            <td class="font-mono text-ink">{{ $baris['kata_sandi'] ?? '(dari berkas)' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <a href="{{ route('admin.users.index') }}" class="btn btn-primary mt-6">Selesai</a>
</x-layouts.app>
