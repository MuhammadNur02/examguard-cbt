<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mahasiswa;
use App\Http\Middleware\NoStore;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/tambah', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/impor', [Admin\UserImportController::class, 'create'])->name('users.import');
        Route::post('/pengguna/impor', [Admin\UserImportController::class, 'store'])->name('users.import.store');
        Route::get('/pengguna/impor/templat', [Admin\UserImportController::class, 'template'])->name('users.import.template');
        Route::post('/pengguna/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/pengguna/{user}/reset-sesi', [Admin\UserController::class, 'resetSession'])->name('users.reset-session');
        Route::patch('/pengguna/{user}/status', [Admin\UserController::class, 'updateStatus'])->name('users.status');
    });

    Route::middleware('role:dosen')->prefix('dosen')->name('dosen.')->group(function () {
        Route::get('/', Dosen\DashboardController::class)->name('dashboard');

        Route::get('/ujian/buat', [Dosen\ExamController::class, 'create'])->name('exams.create');
        Route::post('/ujian', [Dosen\ExamController::class, 'store'])->name('exams.store');

        // Hanya dosen pemilik ujian (ExamPolicy@kelola).
        Route::middleware('can:kelola,exam')->group(function () {
            Route::get('/ujian/{exam}', [Dosen\ExamController::class, 'show'])->name('exams.show');
            Route::get('/ujian/{exam}/ubah', [Dosen\ExamController::class, 'edit'])->name('exams.edit');
            Route::put('/ujian/{exam}', [Dosen\ExamController::class, 'update'])->name('exams.update');
            Route::delete('/ujian/{exam}', [Dosen\ExamController::class, 'destroy'])->name('exams.destroy');
            Route::post('/ujian/{exam}/terbitkan', [Dosen\ExamController::class, 'publish'])->name('exams.publish');
            Route::post('/ujian/{exam}/tarik', [Dosen\ExamController::class, 'unpublish'])->name('exams.unpublish');

            Route::get('/ujian/{exam}/soal/buat', [Dosen\QuestionController::class, 'create'])->name('questions.create');
            Route::post('/ujian/{exam}/soal', [Dosen\QuestionController::class, 'store'])->name('questions.store');
            Route::scopeBindings()->group(function () {
                Route::get('/ujian/{exam}/soal/{question}/ubah', [Dosen\QuestionController::class, 'edit'])->name('questions.edit');
                Route::put('/ujian/{exam}/soal/{question}', [Dosen\QuestionController::class, 'update'])->name('questions.update');
                Route::delete('/ujian/{exam}/soal/{question}', [Dosen\QuestionController::class, 'destroy'])->name('questions.destroy');
            });
        });
    });

    Route::middleware(['role:mahasiswa', NoStore::class])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/', Mahasiswa\DashboardController::class)->name('dashboard');
        Route::get('/nilai', Mahasiswa\GradeHistoryController::class)->name('grades');
        Route::get('/ujian/{exam}', [Mahasiswa\ExamController::class, 'show'])->name('exams.show');
        Route::get('/ujian/{exam}/kerjakan', [Mahasiswa\ExamController::class, 'work'])->name('exams.work');

        Route::middleware('throttle:ujian')->group(function () {
            Route::post('/ujian/{exam}/mulai', [Mahasiswa\AttemptController::class, 'start'])->name('attempts.start');
            Route::get('/ujian/{exam}/soal', [Mahasiswa\AttemptController::class, 'questions'])->name('attempts.questions');
            Route::post('/ujian/{exam}/jawaban', [Mahasiswa\AttemptController::class, 'saveAnswers'])->name('attempts.answers');
            Route::post('/ujian/{exam}/heartbeat', [Mahasiswa\AttemptController::class, 'heartbeat'])->name('attempts.heartbeat');
            Route::post('/ujian/{exam}/pelanggaran', [Mahasiswa\AttemptController::class, 'violation'])->name('attempts.violation');
            Route::post('/ujian/{exam}/kirim', [Mahasiswa\AttemptController::class, 'submit'])->name('attempts.submit');
        });
    });
});
