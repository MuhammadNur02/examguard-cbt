@php
    $aria = fn (string $field) => $errors->has($field) ? 'aria-invalid=true aria-describedby='.$field.'-error' : '';
@endphp
<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="judul" class="form-label">Judul ujian</label>
        <input id="judul" name="judul" type="text" class="form-input" required maxlength="255"
            value="{{ old('judul', $exam->judul) }}" {{ $aria('judul') }}>
        <x-field-error name="judul" />
    </div>

    <div class="sm:col-span-2">
        <label for="mata_kuliah" class="form-label">Mata kuliah</label>
        <input id="mata_kuliah" name="mata_kuliah" type="text" class="form-input" required maxlength="255"
            value="{{ old('mata_kuliah', $exam->mata_kuliah) }}" {{ $aria('mata_kuliah') }}>
        <x-field-error name="mata_kuliah" />
    </div>

    <div>
        <label for="mulai" class="form-label">Waktu mulai (WIB)</label>
        <input id="mulai" name="mulai" type="datetime-local" class="form-input" required
            value="{{ old('mulai', $exam->mulai?->format('Y-m-d\TH:i')) }}" {{ $aria('mulai') }}>
        <x-field-error name="mulai" />
    </div>

    <div>
        <label for="durasi_menit" class="form-label">Durasi (menit)</label>
        <input id="durasi_menit" name="durasi_menit" type="number" class="form-input" required min="1" max="600"
            value="{{ old('durasi_menit', $exam->durasi_menit) }}" {{ $aria('durasi_menit') }}>
        <x-field-error name="durasi_menit" />
    </div>

    <div>
        <label for="batas_pelanggaran" class="form-label">Batas pelanggaran (N)</label>
        <input id="batas_pelanggaran" name="batas_pelanggaran" type="number" class="form-input" required min="1" max="20"
            value="{{ old('batas_pelanggaran', $exam->batas_pelanggaran) }}"
            aria-describedby="batas-help {{ $errors->has('batas_pelanggaran') ? 'batas_pelanggaran-error' : '' }}">
        <p id="batas-help" class="mt-1.5 text-small text-stone-500">Peringatan 1 sampai N ditampilkan; pelanggaran ke-(N+1) mengunci dan mengirim ujian otomatis.</p>
        <x-field-error name="batas_pelanggaran" />
    </div>

    <fieldset class="sm:col-span-2">
        <legend class="form-label">Kelas peserta</legend>
        <p class="mb-2 text-small text-stone-500">Hanya mahasiswa di kelas terpilih yang melihat ujian. Bila tidak ada yang dipilih, ujian terlihat oleh semua mahasiswa.</p>
        @php $terpilih = array_map('intval', (array) old('kelas', $kelasDipilih)); @endphp
        @forelse ($daftarKelas as $kelas)
            <label class="mr-6 inline-flex items-center gap-2 text-small text-ink">
                <input type="checkbox" name="kelas[]" value="{{ $kelas->id }}" class="size-5 accent-maroon-700" @checked(in_array($kelas->id, $terpilih, true))>
                {{ $kelas->nama }}
            </label>
        @empty
            <p class="text-small text-stone-500">Belum ada kelas. Admin dapat membuatnya di menu Kelas.</p>
        @endforelse
        <x-field-error name="kelas" />
        <x-field-error name="kelas.0" id="kelas-0" />
    </fieldset>

    <fieldset class="sm:col-span-2">
        <legend class="form-label">Pengacakan per mahasiswa</legend>
        <div class="mt-1 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-small text-ink">
                <input type="hidden" name="acak_soal" value="0">
                <input type="checkbox" name="acak_soal" value="1" class="size-5 accent-maroon-700"
                    @checked(old('acak_soal', $exam->acak_soal))>
                Acak urutan soal
            </label>
            <label class="flex items-center gap-2 text-small text-ink">
                <input type="hidden" name="acak_opsi" value="0">
                <input type="checkbox" name="acak_opsi" value="1" class="size-5 accent-maroon-700"
                    @checked(old('acak_opsi', $exam->acak_opsi))>
                Acak urutan opsi pilihan ganda
            </label>
        </div>
    </fieldset>
</div>
