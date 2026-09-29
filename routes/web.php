<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\RegistrationController;
use App\Http\Controllers\Public\TicketController;
use App\Http\Controllers\Scanner\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/daftar', [HomeController::class, 'form'])->name('daftar');
Route::post('/daftar', [RegistrationController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('daftar.store');
Route::get('/daftar/sukses/{token}', [RegistrationController::class, 'success'])->name('daftar.sukses');

Route::get('/tiket/{registration:token}', [TicketController::class, 'show'])->name('tiket.show');
Route::get('/cari-tiket', [TicketController::class, 'searchForm'])->name('cari-tiket');
// Throttle per-IP longgar (banyak pengguna seluler berbagi IP lewat CGNAT);
// batas ketat per-kontak (3/jam) ada di TicketController@search dan tidak 429.
Route::post('/cari-tiket', [TicketController::class, 'search'])
    ->middleware('throttle:20,1')
    ->name('cari-tiket.submit');

if (app()->environment('local')) {
    Route::get('/_styleguide', function () {
        return view('public._styleguide');
    });
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });

    Route::middleware('role:scanner')->group(function () {
        Route::get('/scanner', [ScanController::class, 'index'])->name('scanner.index');
    });
});
