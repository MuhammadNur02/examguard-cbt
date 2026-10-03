<x-layouts.app :title="'Rekap Nilai · '.$exam->judul">
    @php
        $F = \App\Support\Format::class;
        $jumlahFinal = $baris->where('final', true)->count();
    @endphp

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-small text-stone-500">
            {{ $exam->mata_kuliah }} · {{ $baris->count() }} peserta · {{ $jumlahFinal }} nilai final.
            Nilai akhir = (skor PG + skor esai final) / skor maksimal × 100.
        </p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('dosen.exams.show', $exam) }}" class="btn btn-ghost btn-sm"><x-icon name="chevron-left" class="size-4" />Detail ujian</a>
            <a href="{{ route('dosen.reports.analysis', $exam) }}" class="btn btn-secondary btn-sm"><x-icon name="chart-column" class="size-4" />Analisis butir</a>
            <a href="{{ route('dosen.reports.export', $exam) }}" class="btn btn-secondary btn-sm"><x-icon name="file-spreadsheet" class="size-4" />Ekspor Excel</a>
            <a href="{{ route('dosen.reports.print', $exam) }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener"><x-icon name="file-text" class="size-4" />Laporan PDF</a>
            <form method="POST" action="{{ route('dosen.reports.publish', $exam) }}"
                data-confirm="Publikasikan semua nilai yang sudah final? Mahasiswa akan dapat melihatnya. Nilai yang belum final tidak ikut.">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm" @disabled($jumlahFinal === 0)><x-icon name="send" class="size-4" />Publikasikan nilai final</button>
            </form>
        </div>
    </div>

    <section class="card mb-6" aria-labelledby="judul-jadwal">
        <h2 id="judul-jadwal" class="text-h3 font-semibold">Publikasi terjadwal</h2>
        @if ($exam->nilai_terbit_pada)
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <p class="flex items-center gap-2 text-ink">
                    <x-icon name="clock" class="size-4 text-maroon-700" />
                    <span>Nilai final akan dipublikasikan otomatis pada <strong>{{ $exam->nilai_terbit_pada->translatedFormat('l, d M Y H:i') }} WIB</strong>.</span>
                </p>
                <form method="POST" action="{{ route('dosen.reports.schedule.cancel', $exam) }}" data-confirm="Batalkan jadwal publikasi nilai?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm"><x-icon name="x" class="size-4" />Batalkan jadwal</button>
                </form>
            </div>
            <p class="mt-1 text-small text-stone-500">Nilai yang belum final pada waktu itu (esai belum dikonfirmasi atau masih mengerjakan) tidak ikut dan dapat dipublikasikan kemudian.</p>
        @else
            <form method="POST" action="{{ route('dosen.reports.schedule', $exam) }}" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="waktu-publikasi" class="form-label">Waktu publikasi (WIB)</label>
                    <input id="waktu-publikasi" name="waktu" type="datetime-local" class="form-input" required
                        min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" value="{{ old('waktu') }}"
                        @error('waktu') aria-invalid="true" aria-describedby="waktu-error" @enderror>
                </div>
                <button type="submit" class="btn btn-secondary"><x-icon name="clock" class="size-4" />Jadwalkan publikasi</button>
                <div class="basis-full"><x-field-error name="waktu" /></div>
            </form>
            <p class="mt-1 text-small text-stone-500">Nilai yang sudah final pada waktu tersebut dipublikasikan otomatis, mis. setelah ujian susulan selesai.</p>
        @endif
    </section>

    <section class="card overflow-hidden p-0" aria-label="Rekap nilai">
        @if ($baris->isEmpty())
            <p class="p-6 text-stone-500">Belum ada peserta yang mengerjakan ujian ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-eg">
                    <thead>
                        <tr>
                            <th scope="col">NIM</th>
                            <th scope="col">Nama</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-right">Skor PG</th>
                            <th scope="col" class="text-right">Skor esai</th>
                            <th scope="col" class="text-right">Maks.</th>
                            <th scope="col" class="text-right">Nilai akhir</th>
                            <th scope="col" class="text-right">Pelanggaran</th>
                            <th scope="col">Publikasi</th>
                            <th scope="col"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($baris as $b)
                            <tr>
                                <td class="font-mono text-ink">{{ $b['nim'] }}</td>
                                <td class="text-ink">{{ $b['nama'] }}</td>
                                <td>{{ $b['status'] }}</td>
                                <td class="num">{{ $F::angka($b['skor_pg']) }}</td>
                                <td class="num">{{ $F::angka($b['skor_esai']) }}</td>
                                <td class="num">{{ $F::angka($b['skor_maksimal']) }}</td>
                                <td class="num">
                                    @if ($b['final'])
                                        <span class="font-semibold text-ink">{{ $F::angka($b['nilai_akhir']) }}</span>
                                    @else
                                        <span class="badge badge-warning">{{ $b['keterangan'] }}</span>
                                    @endif
                                </td>
                                <td class="num">{{ $b['pelanggaran'] }}</td>
                                <td>
                                    @if ($b['dipublikasikan'])
                                        <span class="badge badge-success"><x-icon name="circle-check" class="size-3.5" />Terbit</span>
                                    @else
                                        <span class="badge badge-neutral">Belum</span>
                                    @endif
                                </td>
                                <td><a href="{{ route('dosen.reports.show', [$exam, $b['attempt']]) }}" class="btn btn-ghost btn-sm">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.app>
