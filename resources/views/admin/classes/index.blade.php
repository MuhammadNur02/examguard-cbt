<x-layouts.app title="Kelas">
    <div class="grid items-start gap-6 lg:grid-cols-[1fr_22rem]">
        <section class="card overflow-hidden p-0" aria-label="Daftar kelas">
            @if ($daftar->isEmpty())
                <p class="p-6 text-stone-500">Belum ada kelas.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="table-eg">
                        <thead>
                            <tr>
                                <th scope="col">Kelas</th>
                                <th scope="col">Keterangan</th>
                                <th scope="col" class="text-right">Mahasiswa</th>
                                <th scope="col" class="text-right">Ujian</th>
                                <th scope="col"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($daftar as $kelas)
                                <tr>
                                    <td class="font-medium text-ink">{{ $kelas->nama }}</td>
                                    <td>{{ $kelas->keterangan ?? '–' }}</td>
                                    <td class="num">{{ $kelas->mahasiswa_count }}</td>
                                    <td class="num">{{ $kelas->exams_count }}</td>
                                    <td><a href="{{ route('admin.classes.show', $kelas) }}" class="btn btn-ghost btn-sm">Kelola</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <form method="POST" action="{{ route('admin.classes.store') }}" class="card space-y-4" novalidate>
            @csrf
            <h2 class="text-h3 font-semibold">Tambah kelas</h2>
            <div>
                <label for="nama" class="form-label">Nama kelas</label>
                <input id="nama" name="nama" type="text" maxlength="100" class="form-input" required value="{{ old('nama') }}"
                    placeholder="mis. PTI 2024 B" @error('nama') aria-invalid="true" aria-describedby="nama-error" @enderror>
                <x-field-error name="nama" />
            </div>
            <div>
                <label for="keterangan" class="form-label">Keterangan (opsional)</label>
                <input id="keterangan" name="keterangan" type="text" maxlength="255" class="form-input" value="{{ old('keterangan') }}">
            </div>
            <button type="submit" class="btn btn-primary"><x-icon name="plus" class="size-4" />Tambah</button>
        </form>
    </div>
</x-layouts.app>
