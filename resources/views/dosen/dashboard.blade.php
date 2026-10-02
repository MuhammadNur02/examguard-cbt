<x-layouts.app title="Dashboard Dosen">
    <section aria-labelledby="ringkasan-ujian" class="mb-8">
        <h2 id="ringkasan-ujian" class="sr-only">Ringkasan ujian</h2>
        <div class="grid gap-6 sm:grid-cols-3">
            <x-stat label="Ujian" :value="$exams->count()" icon="clipboard-list" />
            <x-stat label="Terbit" :value="$exams->filter->isPublished()->count()" icon="circle-check" />
            <x-stat label="Draf" :value="$exams->reject->isPublished()->count()" icon="file-text" />
        </div>
    </section>

    <section aria-labelledby="daftar-ujian" class="card overflow-hidden p-0">
        <h2 id="daftar-ujian" class="px-6 pt-6 text-h3 font-semibold">Ujian Anda</h2>
        @if ($exams->isEmpty())
            <p class="px-6 pb-6 pt-2 text-stone-500">Belum ada ujian.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="table-eg">
                    <thead>
                        <tr>
                            <th scope="col">Judul</th>
                            <th scope="col">Mata kuliah</th>
                            <th scope="col">Mulai</th>
                            <th scope="col" class="text-right">Durasi</th>
                            <th scope="col" class="text-right">Soal</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exams as $exam)
                            <tr>
                                <td class="font-medium text-ink">{{ $exam->judul }}</td>
                                <td>{{ $exam->mata_kuliah }}</td>
                                <td>{{ $exam->mulai->translatedFormat('d M Y, H:i') }}</td>
                                <td class="num">{{ $exam->durasi_menit }} mnt</td>
                                <td class="num">{{ $exam->questions_count }}</td>
                                <td>
                                    <span @class(['badge', 'badge-success' => $exam->isPublished(), 'badge-neutral' => ! $exam->isPublished()])>
                                        {{ $exam->status->label() }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
