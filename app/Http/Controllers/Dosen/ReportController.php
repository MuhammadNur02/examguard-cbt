<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamResult;
use App\Models\Question;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
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

    public function index(Exam $exam, ReportService $laporan): View
    {
        return view('dosen.reports.index', ['exam' => $exam, 'baris' => $laporan->baris($exam)]);
    }

    public function show(Exam $exam, ExamAttempt $attempt): View
    {
        $attempt->load(['user', 'result', 'logs', 'answers.option']);
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

    public function publish(Exam $exam, ReportService $laporan): RedirectResponse
    {
        $baris = $laporan->baris($exam);
        $siap = $baris->where('final', true)->where('dipublikasikan', false);
        $belum = $baris->where('final', false)->count();

        ExamResult::whereIn('attempt_id', $siap->pluck('attempt.id'))->update(['dipublikasikan_pada' => now()]);
        AuditLog::catat('nilai_dipublikasikan', $exam, ['jumlah' => $siap->count(), 'belum_final' => $belum]);

        $pesan = "{$siap->count()} nilai dipublikasikan.";
        if ($belum > 0) {
            $pesan .= " {$belum} belum final (menunggu koreksi esai atau masih mengerjakan) dan belum dipublikasikan.";
        }

        return back()->with('status', $pesan);
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
