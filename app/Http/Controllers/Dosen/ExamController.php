<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\ExamRequest;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Kelas;
use App\Services\AttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Kelola ujian (FR-02.1, FR-02.3). Otorisasi kepemilikan lewat ExamPolicy@kelola
 * pada rute. Ujian yang sudah dikerjakan mahasiswa tidak dapat diubah/dihapus.
 */
class ExamController extends Controller
{
    public function create(): View
    {
        return view('dosen.exams.create', [
            'exam' => new Exam(['durasi_menit' => 90, 'batas_pelanggaran' => 3, 'acak_soal' => true, 'acak_opsi' => true]),
            'daftarKelas' => Kelas::orderBy('nama')->get(),
            'kelasDipilih' => [],
        ]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $exam = DB::transaction(function () use ($request) {
            $exam = $request->user()->exams()->create([...$request->dataUjian(), 'status' => ExamStatus::Draft]);
            $exam->kelas()->sync($request->kelasDipilih());
            $exam->access()->updateOrCreate([], ['kode_akses' => $request->kodeAkses()]);

            return $exam;
        });

        return redirect()->route('dosen.exams.show', $exam)
            ->with('status', 'Ujian dibuat sebagai draf. Tambahkan soal, lalu terbitkan.');
    }

    public function show(Exam $exam): View
    {
        $exam->load(['questions.options', 'kelas', 'access'])->loadCount('attempts');

        return view('dosen.exams.show', [
            'exam' => $exam,
            'masalah' => $exam->isPublished() ? [] : $exam->masalahPublikasi(),
        ]);
    }

    public function edit(Exam $exam): View|RedirectResponse
    {
        return $this->tolakBilaSudahDikerjakan($exam) ?? view('dosen.exams.edit', [
            'exam' => $exam,
            'daftarKelas' => Kelas::orderBy('nama')->get(),
            'kelasDipilih' => $exam->kelas()->pluck('classes.id')->all(),
        ]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        if ($tolak = $this->tolakBilaSudahDikerjakan($exam)) {
            return $tolak;
        }

        DB::transaction(function () use ($request, $exam) {
            $exam->update($request->dataUjian());
            $exam->kelas()->sync($request->kelasDipilih());
            $exam->access()->updateOrCreate([], ['kode_akses' => $request->kodeAkses()]);
        });

        return redirect()->route('dosen.exams.show', $exam)->with('status', 'Ujian diperbarui.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        if ($tolak = $this->tolakBilaSudahDikerjakan($exam)) {
            return $tolak;
        }

        $exam->delete();

        return redirect()->route('dosen.dashboard')->with('status', "Ujian \"{$exam->judul}\" dihapus.");
    }

    public function publish(Exam $exam): RedirectResponse
    {
        $masalah = $exam->masalahPublikasi();
        if ($masalah !== []) {
            return back()->with('error', 'Ujian belum dapat diterbitkan. '.implode(' ', $masalah));
        }

        $exam->update(['status' => ExamStatus::Published]);
        AuditLog::catat('ujian_diterbitkan', $exam);

        return back()->with('status', 'Ujian diterbitkan dan kini terlihat oleh mahasiswa.');
    }

    public function unpublish(Exam $exam): RedirectResponse
    {
        if ($exam->sudahDikerjakan()) {
            return back()->with('error', 'Ujian yang sudah dikerjakan mahasiswa tidak dapat ditarik kembali.');
        }

        $exam->update(['status' => ExamStatus::Draft]);
        AuditLog::catat('ujian_ditarik', $exam);

        return back()->with('status', 'Ujian dikembalikan ke draf dan tidak terlihat oleh mahasiswa.');
    }

    /**
     * Duplikat satu klik (FR-02.5): pengaturan, soal, opsi, kelas, dan daftar IP
     * disalin sebagai draf baru. Kode akses tidak disalin agar kode lama yang
     * sudah diketahui mahasiswa tidak terpakai ulang tanpa sengaja.
     */
    public function duplicate(Exam $exam): RedirectResponse
    {
        $salinan = DB::transaction(function () use ($exam) {
            $salinan = $exam->replicate(['status']);
            $salinan->judul = mb_substr($exam->judul, 0, 245).' (salinan)';
            $salinan->status = ExamStatus::Draft;
            $salinan->save();

            foreach ($exam->questions()->with('options')->get() as $question) {
                $baru = $salinan->questions()->create($question->only(['urutan', 'tipe', 'teks', 'bobot', 'kunci_esai', 'keywords']));
                $baru->options()->createMany($question->options->map->only(['label', 'teks', 'is_correct', 'posisi_tetap'])->all());
            }

            $salinan->kelas()->sync($exam->kelas()->pluck('classes.id'));
            $salinan->access()->create(['kode_akses' => null, 'ip_allowlist' => $exam->access?->ip_allowlist]);

            return $salinan;
        });

        AuditLog::catat('ujian_diduplikat', $salinan, ['sumber' => $exam->id]);

        return redirect()->route('dosen.exams.edit', $salinan)
            ->with('status', 'Ujian disalin sebagai draf. Periksa judul, jadwal, dan kode akses, lalu simpan.');
    }

    /**
     * Pratinjau sebagai mahasiswa (FR-02.7): payload sama persis dengan yang
     * diterima mahasiswa, dibangun dari attempt yang tidak disimpan. Tidak ada
     * attempt, jawaban, log, atau nilai yang tercipta.
     */
    public function preview(Request $request, Exam $exam, AttemptService $attempts): View
    {
        $seed = filter_var($request->query('seed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]])
            ?: random_int(1, 2147483647);

        [$urutanSoal, $urutanOpsi] = $attempts->susunUrutan($exam, $seed);
        $attempt = new ExamAttempt(['urutan_soal' => $urutanSoal, 'urutan_opsi' => $urutanOpsi]);

        return view('dosen.exams.preview', [
            'exam' => $exam,
            'soal' => $attempts->soalUntukKlien($attempt),
            'seed' => $seed,
        ]);
    }

    private function tolakBilaSudahDikerjakan(Exam $exam): ?RedirectResponse
    {
        return $exam->sudahDikerjakan()
            ? redirect()->route('dosen.exams.show', $exam)->with('error', 'Ujian sudah dikerjakan mahasiswa sehingga tidak dapat diubah atau dihapus.')
            : null;
    }
}
