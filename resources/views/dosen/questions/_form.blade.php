@php
    $pg = $question->tipe === \App\Enums\QuestionType::Pg;
    $opsiLama = $question->relationLoaded('options') ? $question->options->keyBy('label') : collect();
    $kunciLama = $opsiLama->firstWhere('is_correct', true)?->label;
@endphp

@unless ($question->exists)
    <input type="hidden" name="tipe" value="{{ $question->tipe->value }}">
@endunless

<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="urutan" class="form-label">Nomor urut</label>
        <input id="urutan" name="urutan" type="number" min="1" max="999" class="form-input" required
            value="{{ old('urutan', $question->urutan) }}" @error('urutan') aria-invalid="true" aria-describedby="urutan-error" @enderror>
        <x-field-error name="urutan" />
    </div>
    <div>
        <label for="bobot" class="form-label">Bobot (skor maksimal)</label>
        <input id="bobot" name="bobot" type="number" min="0.5" max="100" step="0.5" class="form-input" required
            value="{{ old('bobot', $question->bobot) }}" @error('bobot') aria-invalid="true" aria-describedby="bobot-error" @enderror>
        <x-field-error name="bobot" />
    </div>
    <div class="sm:col-span-2">
        <label for="teks" class="form-label">Teks soal</label>
        <textarea id="teks" name="teks" rows="4" class="form-input" required maxlength="5000"
            @error('teks') aria-invalid="true" aria-describedby="teks-error" @enderror>{{ old('teks', $question->teks) }}</textarea>
        <x-field-error name="teks" />
    </div>
</div>

@if ($pg)
    <fieldset class="mt-6">
        <legend class="form-label">Opsi jawaban</legend>
        <p class="mb-3 text-small text-stone-500">Isi minimal opsi A dan B secara berurutan. Pilih tepat satu kunci. Opsi "posisi tetap" (mis. "Semua benar") tidak ikut diacak.</p>
        <div class="space-y-3">
            @foreach (\App\Http\Requests\Dosen\QuestionRequest::LABEL as $label)
                @php $opsi = $opsiLama->get($label); @endphp
                <div class="flex flex-wrap items-center gap-3 rounded-md border border-stone-200 bg-white p-3">
                    <label class="flex items-center gap-2 text-small font-semibold text-ink">
                        <input type="radio" name="kunci" value="{{ $label }}" class="size-5 accent-maroon-700"
                            @checked(old('kunci', $kunciLama) === $label) aria-label="Jadikan opsi {{ $label }} sebagai kunci">
                        {{ $label }}
                    </label>
                    <input name="opsi[{{ $label }}][teks]" type="text" maxlength="1000" class="form-input min-w-0 flex-1"
                        aria-label="Teks opsi {{ $label }}" value="{{ old("opsi.{$label}.teks", $opsi?->teks) }}"
                        @error("opsi.{$label}.teks") aria-invalid="true" aria-describedby="opsi-{{ $label }}-teks-error" @enderror>
                    <label class="flex items-center gap-2 text-small text-stone-700">
                        <input type="hidden" name="opsi[{{ $label }}][tetap]" value="0">
                        <input type="checkbox" name="opsi[{{ $label }}][tetap]" value="1" class="size-4 accent-maroon-700"
                            @checked(old("opsi.{$label}.tetap", $opsi?->posisi_tetap))>
                        Posisi tetap
                    </label>
                    @error("opsi.{$label}.teks")
                        <div class="basis-full"><x-field-error :name="'opsi.'.$label.'.teks'" /></div>
                    @enderror
                </div>
            @endforeach
        </div>
        <x-field-error name="opsi" />
        <x-field-error name="kunci" />
    </fieldset>
@else
    <div class="mt-6 grid gap-5">
        <div>
            <label for="kunci_esai" class="form-label">Kunci jawaban patokan</label>
            <textarea id="kunci_esai" name="kunci_esai" rows="5" class="form-input" required maxlength="5000"
                aria-describedby="kunci-help @error('kunci_esai') kunci_esai-error @enderror"
                @error('kunci_esai') aria-invalid="true" @enderror>{{ old('kunci_esai', $question->kunci_esai) }}</textarea>
            <p id="kunci-help" class="mt-1.5 text-small text-stone-500">Dipakai sebagai acuan TF-IDF dan Cosine Similarity; skor sistem hanya rekomendasi.</p>
            <x-field-error name="kunci_esai" />
        </div>
        <div>
            <label for="keywords" class="form-label">Kata kunci wajib (opsional)</label>
            <textarea id="keywords" name="keywords" rows="2" class="form-input" maxlength="2000"
                aria-describedby="keywords-help @error('keywords') keywords-error @enderror"
                @error('keywords') aria-invalid="true" @enderror>{{ old('keywords', implode(', ', $question->keywords ?? [])) }}</textarea>
            <p id="keywords-help" class="mt-1.5 text-small text-stone-500">Pisahkan dengan koma atau baris baru. Ditampilkan ke dosen sebagai checklist saat koreksi.</p>
            <x-field-error name="keywords" />
        </div>
    </div>
@endif
