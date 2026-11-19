<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Semua role: melihat barang
    Route::get('/barang', [ProductController::class, 'index'])->name('products.index');

    // Hanya admin: mengelola barang (harus sebelum /barang/{product})
    Route::middleware('role:admin')->group(function () {
        Route::get('/barang/tambah', [ProductController::class, 'create'])->name('products.create');
        Route::post('/barang', [ProductController::class, 'store'])->name('products.store');
        Route::get('/barang/{product}/ubah', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/barang/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/barang/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    });

    Route::get('/barang/{product}', [ProductController::class, 'show'])->name('products.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Placeholder halaman khusus admin; diganti Manajemen Pengguna di Tahap 5.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/pengguna', fn () => 'Halaman khusus admin (placeholder)')
        ->name('users.index');
});

require __DIR__.'/auth.php';
