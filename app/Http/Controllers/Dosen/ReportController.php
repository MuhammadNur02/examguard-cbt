<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamLog;
use App\Models\Question;
use App\Services\PublicationService;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan nilai (FR-09.1, FR-09.2): rekap, detail jawaban per mahasiswa,
 * ekspor Excel, dan publikasi nilai yang sudah final (FR-07.3, D-16).
 */
class ReportController extends Controller
{
    private const KOLOM = [
        'No', 'NIM', 'Nama', 'Status', 'Skor PG', 'Skor Esai', 'Skor Maksimal',
        'Nilai Akhir', 'Keterangan', 'Pelanggaran', 'Dikirim', 'Dipublikasikan',
    ];

    public function index(Exam $exam, ReportService $laporan, PublicationService $publikasi): View
    {
        $publikasi->jalankanTerjadwal();

        return view('dosen.reports.index', ['exam' => $exam->refresh(), 'baris' => $laporan->baris($exam)]);
    }

    public function show(Exam $exam, ExamAttempt $attempt): View
    {
        $attempt->load(['user', 'result', 'logs.pemaaf:id,nama', 'answers.option']);
        $questions = Question::with('options')->whereIn('id', $attempt->urutan_soal)->get()->keyBy('id');
        $nomorAsli = $exam->questions()->pluck('id')->flip();

        return view('dosen.reports.show', [
            'exam' => $exam,
            'attempt' => $attempt,
            'soal' => collect($attempt->urutan_soal)
                ->filter(fn (int $id) => $questions->has($id))
                ->values()
                ->map(fn (int $id, int $i) => [
                    'nomor' => $i + 1,
                    'nomor_asli' => ($nomorAsli[$id] ?? 0) + 1,
                    'question' => $questions[$id],
                    'answer' => $attempt->answers->firstWhere('question_id', $id),
                ]),
        ]);
    }

    public function export(Exam $exam, ReportService $laporan): StreamedResponse
    {
        $baris = $laporan->baris($exam);
        $namaBerkas = 'rekap-nilai-'.Str::slug($exam->judul).'-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($baris) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(new Row(array_map(fn ($judul) => new StringCell($judul, (new Style)->withFontBold(true)), self::KOLOM)));

            foreach ($baris->values() as $i => $b) {
                $writer->addRow(new Row([
                    $this->sel($i + 1),
                    $this->sel($b['nim']),
                    $this->sel($b['nama']),
                    $this->sel($b['status']),
                    $this->sel($b['skor_pg']),
                    $this->sel($b['skor_esai']),
                    $this->sel($b['skor_maksimal']),
                    $this->sel($b['nilai_akhir']),
                    $this->sel($b['keterangan']),
                    $this->sel($b['pelanggaran']),
                    $this->sel($b['dikirim']?->format('Y-m-d H:i')),
                    $this->sel($b['dipublikasikan'] ? 'Ya' : 'Belum'),
                ]));
            }

            $writer->close();
        }, $namaBerkas, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function publish(Exam $exam, PublicationService $publikasi): RedirectResponse
    {
        ['jumlah' => $jumlah, 'belum_final' => $belum] = $publikasi->terbitkan($exam, now());
        AuditLog::catat('nilai_dipublikasikan', $exam, ['jumlah' => $jumlah, 'belum_final' => $belum]);

        $pesan = "{$jumlah} nilai dipublikasikan.";
        if ($belum > 0) {
            $pesan .= " {$belum} belum final (menunggu koreksi esai atau masih mengerjakan) dan belum dipublikasikan.";
        }

        return back()->with('status', $pesan);
    }

    /**
     * FR-09.3: laporan rekap + pelanggaran per mahasiswa yang siap dicetak.
     * PDF dibuat lewat dialog cetak peramban ("Simpan sebagai PDF"), tanpa
     * pustaka PDF tambahan (D-53).
     */
    public function print(Exam $exam, ReportService $laporan): View
    {
        $baris = $laporan->baris($exam);
        $logs = ExamLog::whereIn('attempt_id', $baris->pluck('attempt.id'))
            ->with('pemaaf:id,nama')
            ->orderBy('waktu')
            ->get()
            ->groupBy('attempt_id');

        return view('dosen.reports.print', ['exam' => $exam->load('dosen'), 'baris' => $baris, 'logs' => $logs]);
    }

    /** FR-08.1: jadwalkan publikasi nilai final pada waktu tertentu. */
    public function schedule(Request $request, Exam $exam): RedirectResponse
    {
        $data = $request->validate(['waktu' => ['required', 'date', 'after:now']], [
            'waktu.after' => 'Waktu publikasi harus di masa depan.',
        ], ['waktu' => 'waktu publikasi']);

        $waktu = Carbon::parse($data['waktu'])->startOfMinute();
        $exam->update(['nilai_terbit_pada' => $waktu]);
        AuditLog::catat('publikasi_nilai_dijadwalkan', $exam, ['waktu' => $waktu->toIso8601String()]);

        return back()->with('status', 'Nilai final akan dipublikasikan otomatis pada '.$waktu->translatedFormat('d M Y H:i').' WIB.');
    }

    public function cancelSchedule(Exam $exam): RedirectResponse
    {
        $exam->update(['nilai_terbit_pada' => null]);
        AuditLog::catat('publikasi_nilai_dibatalkan', $exam);

        return back()->with('status', 'Jadwal publikasi nilai dibatalkan.');
    }

    /**
     * Sel eksplisit: teks selalu StringCell agar nilai berawalan "=" tidak
     * menjadi formula (Cell::fromValue mengubahnya menjadi FormulaCell).
     */
    private function sel(string|int|float|null $nilai): Cell
    {
        return match (true) {
            $nilai === null, $nilai === '' => new EmptyCell(null, null),
            is_int($nilai), is_float($nilai) => new NumericCell($nilai, null),
            default => new StringCell($nilai, null),
        };
    }
}
