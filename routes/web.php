<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\RegistrationAdminController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\TicketController;
use App\Http\Controllers\Scanner\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/daftar', [HomeController::class, 'form'])->name('daftar');
// Limiter 'daftar-submit': 10/menit per sesi, 300/menit per IP (CGNAT); saat
// terlampaui kembali ke form dengan isian tetap, lihat AppServiceProvider.
Route::post('/daftar', [RegistrationController::class, 'store'])
    ->middleware('throttle:daftar-submit')
    ->name('daftar.store');
// Captcha cadangan saat Turnstile gagal. Limiter 'daftar-captcha-image':
// 20/menit per sesi, 120/menit per IP (CGNAT), lihat AppServiceProvider.
Route::get('/daftar/captcha', [RegistrationController::class, 'captcha'])
    ->middleware('throttle:daftar-captcha-image')
    ->name('daftar.captcha');
Route::get('/daftar/sukses/{token}', [RegistrationController::class, 'success'])->name('daftar.sukses');

Route::get('/tiket/{registration:token}', [TicketController::class, 'show'])->name('tiket.show');
Route::get('/cari-tiket', [TicketController::class, 'searchForm'])->name('cari-tiket');
// Throttle per-IP longgar (banyak pengguna seluler berbagi IP lewat CGNAT);
// batas ketat per-kontak (3/jam) ada di TicketController@search dan tidak 429.
Route::post('/cari-tiket', [TicketController::class, 'search'])
    ->middleware('throttle:120,1')
    ->name('cari-tiket.submit');

if (app()->environment('local')) {
    Route::get('/_styleguide', function () {
        return view('public._styleguide');
    });
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    // Throttle per IP (20/menit) di samping throttle username+IP (5/menit) di
    // LoginController: yang kedua tidak menahan penyemprotan banyak username.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/dashboard-data', [DashboardController::class, 'data'])->name('dashboard.data');
        Route::post('/dashboard/resend-failed', [DashboardController::class, 'resendFailed'])->name('dashboard.resend-failed');

        Route::get('/events', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events', [EventController::class, 'update'])->name('events.update');

        Route::get('/registrations', [RegistrationAdminController::class, 'index'])->name('registrations.index');
        Route::get('/registrations/{registration}', [RegistrationAdminController::class, 'show'])->name('registrations.show');
        Route::post('/registrations/{registration}/cancel', [RegistrationAdminController::class, 'cancel'])->name('registrations.cancel');
        Route::post('/registrations/{registration}/resend', [RegistrationAdminController::class, 'resend'])->name('registrations.resend');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/export', ExportController::class)->name('export');
    });

    Route::middleware('role:scanner')->group(function () {
        Route::get('/scanner', [ScanController::class, 'index'])->name('scanner.index');
        Route::post('/scan', [ScanController::class, 'scan'])->name('scanner.scan');
        Route::post('/scan/cari', [ScanController::class, 'search'])->name('scanner.search');
        Route::post('/scan/{registration}/redeem', [ScanController::class, 'redeem'])->name('scanner.redeem');
    });
});
