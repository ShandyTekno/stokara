<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockInController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
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

    // Semua role: mencatat dan melihat riwayat barang masuk/keluar
    // (/tambah harus sebelum /{...})
    Route::get('/barang-masuk', [StockInController::class, 'index'])->name('stock-ins.index');
    Route::get('/barang-masuk/tambah', [StockInController::class, 'create'])->name('stock-ins.create');
    Route::post('/barang-masuk', [StockInController::class, 'store'])->name('stock-ins.store');
    Route::get('/barang-masuk/{stockIn}', [StockInController::class, 'show'])->name('stock-ins.show');

    Route::get('/barang-keluar', [StockOutController::class, 'index'])->name('stock-outs.index');
    Route::get('/barang-keluar/tambah', [StockOutController::class, 'create'])->name('stock-outs.create');
    Route::post('/barang-keluar', [StockOutController::class, 'store'])->name('stock-outs.store');
    Route::get('/barang-keluar/{stockOut}', [StockOutController::class, 'show'])->name('stock-outs.show');

    // Hanya admin
    Route::middleware('role:admin')->group(function () {
        // Barang (harus sebelum /barang/{product})
        Route::get('/barang/tambah', [ProductController::class, 'create'])->name('products.create');
        Route::post('/barang', [ProductController::class, 'store'])->name('products.store');
        Route::get('/barang/{product}/ubah', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/barang/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/barang/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Kategori
        Route::get('/kategori', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('/kategori', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/kategori/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/kategori/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        // Pemasok (/tambah sebelum /{supplier})
        Route::get('/pemasok', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/pemasok/tambah', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/pemasok', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/pemasok/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/pemasok/{supplier}/ubah', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/pemasok/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/pemasok/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Manajemen pengguna
        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/tambah', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/pengguna/{user}/status', [UserController::class, 'toggle'])->name('users.toggle');

        // Aktivitas pengguna
        Route::get('/aktivitas', ActivityController::class)->name('activities.index');
    });

    Route::get('/barang/{product}', [ProductController::class, 'show'])->name('products.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
