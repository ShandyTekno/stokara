<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // {user} hanya bisa menunjuk akun di toko pengguna yang sedang login.
        Route::bind('user', function (string $value) {
            $storeId = request()->user()?->store_id;
            abort_unless($storeId, 404);

            return User::where('store_id', $storeId)->findOrFail($value);
        });
    }
}
