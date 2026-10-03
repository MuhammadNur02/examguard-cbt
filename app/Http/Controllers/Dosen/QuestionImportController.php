<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Services\QuestionImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Impor soal CSV/Excel (FR-02.4); hanya untuk ujian draf milik dosen. */
class QuestionImportController extends Controller
{
    /** Contoh di templat; tes memastikan contoh ini lolos validasi impor. */
    private const CONTOH = [
        ['pg', 'Protokol yang mengamankan komunikasi HTTP dengan enkripsi adalah ...', '1', 'FTP', 'HTTPS', 'SMTP', 'Telnet', 'Semua jawaban salah', 'B', 'E', '', ''],
        ['pg', 'Tag HTML untuk membuat tautan adalah ...', '1', '<a>', '<link>', '<href>', '<url>', '', 'A', '', '', ''],
        ['esai', 'Jelaskan perbedaan metode GET dan POST pada HTTP.', '10', '', '', '', '', '', '', '', 'GET mengirim data melalui URL dan dipakai untuk mengambil data, sedangkan POST mengirim data di badan permintaan dan dipakai untuk mengirim atau mengubah data.', 'URL, badan permintaan, mengambil, mengirim'],
    ];

    public function create(Exam $exam): View|RedirectResponse
    {
        if ($tolak = QuestionController::tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        return view('dosen.questions.import', ['exam' => $exam, 'maksBaris' => QuestionImporter::MAKS_BARIS]);
    }

    public function store(Request $request, Exam $exam, QuestionImporter $importer): RedirectResponse
    {
        if ($tolak = QuestionController::tolakBilaBukanDraf($exam)) {
            return $tolak;
        }

        $request->validate([
            'berkas' => ['required', 'file', 'max:2048', 'extensions:csv,txt,xlsx', 'mimetypes:text/plain,text/csv,application/csv,application/zip,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/octet-stream'],
        ], [], ['berkas' => 'berkas soal']);

        $berkas = $request->file('berkas');
        $hasil = $importer->validasi($berkas->getRealPath(), $berkas->getClientOriginalExtension(), $exam);

        if ($hasil['galat'] !== []) {
            return back()->with('galat_impor', $hasil['galat']);
        }

        $jumlah = $importer->simpan($exam, $hasil['soal']);
        AuditLog::catat('soal_diimpor', $exam, ['jumlah' => $jumlah]);

        return redirect()->route('dosen.exams.show', $exam)->with('status', "{$jumlah} soal berhasil diimpor.");
    }

    public function template(Request $request): StreamedResponse|BinaryFileResponse
    {
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ([QuestionImporter::KOLOM, ...self::CONTOH] as $baris) {
                    fputcsv($out, $baris, ',', '"', '');
                }
                fclose($out);
            }, 'templat-impor-soal.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $options = new Options;
        $options->setColumnWidth(50, 2);
        $options->setColumnWidth(18, 4, 5, 6, 7, 8);
        $options->setColumnWidth(50, 11);
        $options->setColumnWidth(30, 12);

        $path = tempnam(sys_get_temp_dir(), 'templat');
        $writer = new Writer($options);
        $writer->openToFile($path);
        $tebal = (new Style)->withFontBold(true);
        $writer->addRow(new Row(array_map(fn ($k) => new StringCell($k, $tebal), QuestionImporter::KOLOM)));
        foreach (self::CONTOH as $baris) {
            $writer->addRow(new Row(array_map(fn ($nilai) => new StringCell($nilai, null), $baris)));
        }
        $writer->close();

        return response()->download($path, 'templat-impor-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
