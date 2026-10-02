<x-layouts.app title="Riwayat Nilai">
    <section class="card overflow-hidden p-0" aria-label="Riwayat nilai">
        @if ($riwayat->isEmpty())
            <p class="p-6 text-stone-500">Belum ada ujian yang Anda selesaikan.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-eg">
                    <thead>
                        <tr>
                            <th scope="col">Ujian</th>
                            <th scope="col">Mata kuliah</th>
                            <th scope="col">Dikirim</th>
                            <th scope="col" class="text-right">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($riwayat as ['attempt' => $attempt, 'nilai' => $nilai])
                            <tr>
                                <td class="font-medium text-ink">{{ $attempt->exam->judul }}</td>
                                <td>{{ $attempt->exam->mata_kuliah }}</td>
                                <td>{{ $attempt->selesai?->translatedFormat('d M Y, H:i') }}</td>
                                <td class="num">
                                    @if ($nilai !== null)
                                        <span class="font-display text-h3 font-semibold text-maroon-900">{{ \App\Support\Format::angka($nilai) }}</span>
                                    @else
                                        <span class="badge badge-neutral">Belum dipublikasikan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
