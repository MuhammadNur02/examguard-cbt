<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dosen;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Mahasiswa;
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
    });

    Route::middleware('role:mahasiswa')->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/', Mahasiswa\DashboardController::class)->name('dashboard');
    });
});
