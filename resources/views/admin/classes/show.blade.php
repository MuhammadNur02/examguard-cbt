<x-layouts.app :title="'Kelas '.$kelas->nama">
    @if (session('galat_impor'))
        <div class="alert alert-danger mb-6" role="alert">
            <x-icon name="circle-alert" class="mt-0.5 size-5" />
            <div>
                <p class="font-semibold">Impor dibatalkan. Tidak ada anggota yang ditambahkan:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach (session('galat_impor') as $galat)
                        <li>{{ $galat }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <a href="{{ route('admin.classes.index') }}" class="btn btn-ghost btn-sm mb-4"><x-icon name="chevron-left" class="size-4" />Daftar kelas</a>

    <div class="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden p-0" aria-labelledby="judul-anggota">
            <h2 id="judul-anggota" class="px-6 pt-6 text-h3 font-semibold">Anggota ({{ $anggota->count() }})</h2>
            @if ($anggota->isEmpty())
                <p class="px-6 pb-6 pt-2 text-stone-500">Belum ada mahasiswa di kelas ini.</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="table-eg">
                        <thead><tr><th scope="col">NIM</th><th scope="col">Nama</th><th scope="col"><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody>
                            @foreach ($anggota as $mhs)
                                <tr>
                                    <td class="font-mono text-ink">{{ $mhs->nim_nidn }}</td>
                                    <td class="text-ink">{{ $mhs->nama }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('admin.classes.members.remove', [$kelas, $mhs]) }}" data-confirm="Keluarkan {{ $mhs->nim_nidn }} dari kelas ini?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="user-x" class="size-4" />Keluarkan</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.classes.members.add', $kelas) }}" class="card space-y-4" novalidate>
                @csrf
                <h2 class="text-h3 font-semibold">Tambah anggota</h2>
                <div>
                    <label for="nim_nidn" class="form-label">NIM mahasiswa</label>
                    <input id="nim_nidn" name="nim_nidn" type="text" maxlength="30" class="form-input font-mono" required value="{{ old('nim_nidn') }}"
                        @error('nim_nidn') aria-invalid="true" aria-describedby="nim_nidn-error" @enderror>
                    <x-field-error name="nim_nidn" />
                </div>
                <button type="submit" class="btn btn-primary"><x-icon name="user-plus" class="size-4" />Tambah</button>
            </form>

            <form method="POST" action="{{ route('admin.classes.members.import', $kelas) }}" enctype="multipart/form-data" class="card space-y-4" novalidate>
                @csrf
                <h2 class="text-h3 font-semibold">Impor anggota (CSV)</h2>
                <p class="text-small text-stone-500">Satu kolom berjudul <code class="font-mono">nim_nidn</code>. Akun mahasiswa harus sudah ada (impor akun di menu Akun Pengguna).</p>
                <input id="berkas" name="berkas" type="file" accept=".csv,text/csv" required aria-label="Berkas CSV anggota"
                    class="block w-full text-small file:mr-4 file:rounded-md file:border-0 file:bg-maroon-100 file:px-4 file:py-2.5 file:font-semibold file:text-maroon-700">
                <x-field-error name="berkas" />
                <button type="submit" class="btn btn-secondary"><x-icon name="upload" class="size-4" />Impor</button>
            </form>

            <form method="POST" action="{{ route('admin.classes.update', $kelas) }}" class="card space-y-4" novalidate>
                @csrf
                @method('PUT')
                <h2 class="text-h3 font-semibold">Ubah kelas</h2>
                <div>
                    <label for="nama-kelas" class="form-label">Nama kelas</label>
                    <input id="nama-kelas" name="nama" type="text" maxlength="100" class="form-input" required value="{{ old('nama', $kelas->nama) }}">
                    <x-field-error name="nama" />
                </div>
                <div>
                    <label for="keterangan-kelas" class="form-label">Keterangan</label>
                    <input id="keterangan-kelas" name="keterangan" type="text" maxlength="255" class="form-input" value="{{ old('keterangan', $kelas->keterangan) }}">
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-secondary"><x-icon name="save" class="size-4" />Simpan</button>
                </div>
            </form>

            @if ($kelas->exams_count === 0)
                <form method="POST" action="{{ route('admin.classes.destroy', $kelas) }}" data-confirm="Hapus kelas {{ $kelas->nama }}? Keanggotaan ikut terhapus; akun mahasiswa tidak.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm text-status-danger"><x-icon name="trash-2" class="size-4" />Hapus kelas</button>
                </form>
            @else
                <p class="text-small text-stone-500">Kelas dipakai {{ $kelas->exams_count }} ujian sehingga tidak dapat dihapus.</p>
            @endif
        </div>
    </div>
</x-layouts.app>
