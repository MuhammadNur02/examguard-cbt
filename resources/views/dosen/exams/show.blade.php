<x-layouts.app :title="$exam->judul">
    @php
        $draf = ! $exam->isPublished();
        $dikerjakan = $exam->attempts_count > 0;
    @endphp

    <section class="card card-important mb-6" aria-labelledby="info-ujian">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="label-caps text-stone-500">{{ $exam->mata_kuliah }}</p>
                <h2 id="info-ujian" class="mt-1 font-display text-h1 font-semibold text-maroon-900">{{ $exam->judul }}</h2>
            </div>
            <span @class(['badge', 'badge-success' => ! $draf, 'badge-neutral' => $draf])>
                <x-icon :name="$draf ? 'file-text' : 'circle-check'" class="size-3.5" />{{ $exam->status->label() }}
            </span>
        </div>

        <dl class="mt-5 grid gap-x-8 gap-y-3 text-small sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-stone-500">Jadwal</dt><dd class="font-medium text-ink">{{ $exam->mulai->translatedFormat('l, d M Y H:i') }} – {{ $exam->selesaiPada()->format('H:i') }} WIB</dd></div>
            <div><dt class="text-stone-500">Durasi</dt><dd class="font-medium text-ink">{{ $exam->durasi_menit }} menit</dd></div>
            <div><dt class="text-stone-500">Batas pelanggaran</dt><dd class="font-medium text-ink">{{ $exam->batas_pelanggaran }} (ke-{{ $exam->batas_pelanggaran + 1 }} mengunci ujian)</dd></div>
            <div><dt class="text-stone-500">Acak soal / opsi</dt><dd class="font-medium text-ink">{{ $exam->acak_soal ? 'Ya' : 'Tidak' }} / {{ $exam->acak_opsi ? 'Ya' : 'Tidak' }}</dd></div>
            <div><dt class="text-stone-500">Peserta yang memulai</dt><dd class="font-medium text-ink">{{ $exam->attempts_count }}</dd></div>
            <div><dt class="text-stone-500">Kelas peserta</dt><dd class="font-medium text-ink">{{ $exam->kelas->pluck('nama')->join(', ') ?: 'Semua mahasiswa' }}</dd></div>
            <div><dt class="text-stone-500">Kode akses</dt><dd class="font-medium text-ink">
                @if ($exam->access?->kode_akses)
                    <span class="font-mono tracking-wider">{{ $exam->access->kode_akses }}</span>
                @else
                    Tanpa kode
                @endif
            </dd></div>
        </dl>

        @if ($exam->kelas->isEmpty())
            <p class="alert alert-warning mt-5" role="note">
                <x-icon name="triangle-alert" class="mt-0.5 size-5" />
                Ujian ini belum ditetapkan ke kelas sehingga terlihat oleh semua mahasiswa. Pilih kelas lewat "Ubah ujian" bila perlu.
            </p>
        @endif

        <div class="mt-6 flex flex-wrap gap-2">
            @unless ($draf)
                <a href="{{ route('dosen.monitor', $exam) }}" class="btn btn-primary btn-sm"><x-icon name="monitor" class="size-4" />Live Monitor</a>
                <a href="{{ route('dosen.grading.index', $exam) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" />Koreksi Esai</a>
                <a href="{{ route('dosen.reports.index', $exam) }}" class="btn btn-secondary btn-sm"><x-icon name="chart-column" class="size-4" />Rekap Nilai</a>
            @endunless
            @unless ($dikerjakan)
                <a href="{{ route('dosen.exams.edit', $exam) }}" class="btn btn-secondary btn-sm"><x-icon name="pencil" class="size-4" />Ubah ujian</a>
            @endunless
            @if ($draf)
                <form method="POST" action="{{ route('dosen.exams.publish', $exam) }}" data-confirm="Terbitkan ujian? Ujian akan terlihat oleh mahasiswa.">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm" @disabled($masalah !== [])><x-icon name="send" class="size-4" />Terbitkan</button>
                </form>
            @elseif (! $dikerjakan)
                <form method="POST" action="{{ route('dosen.exams.unpublish', $exam) }}" data-confirm="Tarik ujian ke draf? Mahasiswa tidak akan melihatnya.">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm"><x-icon name="eye-off" class="size-4" />Tarik ke draf</button>
                </form>
            @endif
            @if ($exam->questions->isNotEmpty())
                <a href="{{ route('dosen.exams.preview', $exam) }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener"><x-icon name="eye" class="size-4" />Pratinjau</a>
            @endif
            <form method="POST" action="{{ route('dosen.exams.duplicate', $exam) }}" data-confirm="Duplikat ujian ini beserta semua soalnya sebagai draf baru?">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="copy" class="size-4" />Duplikat</button>
            </form>
            @unless ($dikerjakan)
                <form method="POST" action="{{ route('dosen.exams.destroy', $exam) }}" data-confirm="Hapus ujian beserta semua soalnya? Tindakan ini tidak dapat dibatalkan.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm text-status-danger"><x-icon name="trash-2" class="size-4" />Hapus</button>
                </form>
            @endunless
        </div>
    </section>

    @if ($masalah !== [])
        <div class="alert alert-warning mb-6" role="status">
            <x-icon name="triangle-alert" class="mt-0.5 size-5" />
            <div>
                <p class="font-semibold">Ujian belum dapat diterbitkan:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($masalah as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <section aria-labelledby="judul-soal">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="judul-soal" class="text-h2 font-semibold">
                Soal ({{ $exam->questions->count() }}) · total bobot {{ \App\Support\Format::angka($exam->questions->sum('bobot')) }}
            </h2>
            @if ($draf)
                <div class="flex gap-2">
                    <a href="{{ route('dosen.questions.create', [$exam, 'tipe' => 'pg']) }}" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" />Pilihan ganda</a>
                    <a href="{{ route('dosen.questions.create', [$exam, 'tipe' => 'esai']) }}" class="btn btn-secondary btn-sm"><x-icon name="plus" class="size-4" />Esai</a>
                    <a href="{{ route('dosen.questions.import', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="upload" class="size-4" />Impor</a>
                </div>
            @else
                <p class="text-small text-stone-500">Soal hanya dapat diubah saat ujian berstatus draf.</p>
            @endif
        </div>

        @forelse ($exam->questions as $i => $question)
            <article class="card mb-4" aria-labelledby="soal-{{ $question->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 id="soal-{{ $question->id }}" class="flex items-center gap-2 text-h3 font-semibold">
                        Soal {{ $i + 1 }}
                        <span class="badge badge-info">{{ $question->tipe->label() }}</span>
                        <span class="badge badge-neutral">Bobot {{ \App\Support\Format::angka($question->bobot) }}</span>
                    </h3>
                    @if ($draf)
                        <div class="flex gap-1">
                            <a href="{{ route('dosen.questions.edit', [$exam, $question]) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="size-4" />Ubah</a>
                            <form method="POST" action="{{ route('dosen.questions.destroy', [$exam, $question]) }}" data-confirm="Hapus soal {{ $i + 1 }}?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-sm text-status-danger"><x-icon name="trash-2" class="size-4" />Hapus</button>
                            </form>
                        </div>
                    @endif
                </div>

                <p class="mt-3 whitespace-pre-line text-question text-ink">{{ $question->teks }}</p>

                @if ($question->isPg())
                    <ol class="mt-4 space-y-2">
                        @foreach ($question->options as $option)
                            <li @class([
                                'flex items-start gap-3 rounded-md border px-4 py-2.5',
                                'border-status-success bg-status-success-bg' => $option->is_correct,
                                'border-stone-200' => ! $option->is_correct,
                            ])>
                                <span class="font-semibold text-ink">{{ $option->label }}.</span>
                                <span class="flex-1 text-ink">{{ $option->teks }}</span>
                                @if ($option->posisi_tetap)
                                    <span class="badge badge-neutral"><x-icon name="lock" class="size-3.5" />Posisi tetap</span>
                                @endif
                                @if ($option->is_correct)
                                    <span class="badge badge-success"><x-icon name="check" class="size-3.5" />Kunci</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <div class="mt-4 rounded-md bg-gold-100 px-4 py-3">
                        <p class="label-caps text-stone-700">Kunci patokan</p>
                        <p class="mt-1 whitespace-pre-line text-ink">{{ $question->kunci_esai }}</p>
                    </div>
                    @if ($question->keywords)
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span class="text-small text-stone-500">Kata kunci:</span>
                            @foreach ($question->keywords as $kata)
                                <span class="badge badge-neutral">{{ $kata }}</span>
                            @endforeach
                        </div>
                    @endif
                @endif
            </article>
        @empty
            <p class="card text-stone-500">Belum ada soal. Tambahkan soal pilihan ganda atau esai.</p>
        @endforelse
    </section>
</x-layouts.app>
