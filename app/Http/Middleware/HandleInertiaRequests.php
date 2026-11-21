<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('store');
        $store = $user?->store ?? Store::first();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'role' => $user->role->value,
                    'store' => $user->store ? [
                        'id' => $user->store->id,
                        'name' => $user->store->name,
                    ] : null,
                ] : null,
            ],
            'site' => [
                'app_name' => $store?->app_name ?: config('app.name', 'STOKARA'),
                'store_name' => $store?->name ?: 'Toko',
                'tagline' => $store?->tagline ?: 'Sistem Manajemen Stok & Kasir Modern',
                'phone' => $store?->phone,
                'address' => $store?->address,
                'logo_url' => $store?->logo_url,
                'settings' => $store ? $store->resolvedSettings() : Store::DEFAULT_SETTINGS,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
