<x-layouts.app :title="'Koreksi Esai · '.$exam->judul">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-small text-stone-500">
            Skor sistem (TF-IDF + Cosine Similarity × bobot) hanya rekomendasi. Nilai akhir adalah keputusan Anda.
            Hitung skor rekomendasi setelah semua peserta selesai; layanan NLP dan queue worker harus berjalan.
        </p>
        <div class="flex gap-2">
            <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" />Detail ujian</a>
            <form method="POST" action="{{ route('dosen.grading.score', $exam) }}" data-confirm="Hitung (ulang) skor rekomendasi semua soal esai? Skor yang sudah Anda konfirmasi tidak berubah.">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm"><x-icon name="refresh-cw" class="size-4" />Hitung skor rekomendasi</button>
            </form>
        </div>
    </div>

    @forelse ($soal as ['question' => $question, 'total' => $total, 'direkomendasikan' => $direkomendasikan, 'dikonfirmasi' => $dikonfirmasi])
        <article class="card mb-4 flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0 flex-1">
                <h2 class="text-h3 font-semibold">Soal esai · bobot {{ \App\Support\Format::angka($question->bobot) }}</h2>
                <p class="mt-1 line-clamp-2 text-stone-700">{{ $question->teks }}</p>
                <p class="mt-2 flex flex-wrap gap-2 text-small">
                    <span class="badge {{ $total > 0 && $dikonfirmasi === $total ? 'badge-success' : 'badge-neutral' }}">Dikonfirmasi {{ $dikonfirmasi }}/{{ $total }}</span>
                    <span class="badge badge-info">Ada rekomendasi {{ $direkomendasikan }}/{{ $total }}</span>
                </p>
            </div>
            <a href="{{ route('dosen.grading.show', [$exam, $question]) }}" class="btn btn-secondary btn-sm">
                <x-icon name="pencil" class="size-4" />Koreksi
            </a>
        </article>
    @empty
        <p class="card text-stone-500">Ujian ini tidak memiliki soal esai.</p>
    @endforelse
</x-layouts.app>
