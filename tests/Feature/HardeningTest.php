<?php

namespace Tests\Feature;

use App\Models\Concerns\BelongsToStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_route_requires_login_or_is_explicitly_public(): void
    {
        $unprotected = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                $uri = $route->uri();

                if (in_array($uri, ['/', 'up', 'sanctum/csrf-cookie'], true)
                    || str_starts_with($uri, 'storage/')
                    || str_starts_with($uri, '_ignition')) {
                    return false;
                }

                $middleware = $route->gatherMiddleware();

                return ! in_array('auth', $middleware, true) && ! in_array('guest', $middleware, true);
            })
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'Route tanpa auth atau guest: '.implode(', ', $unprotected));
    }

    public function test_only_known_routes_are_open_to_karyawan(): void
    {
        $allowed = [
            'dashboard', 'products.index', 'products.show',
            'stock-ins.*', 'stock-outs.*', 'sales.*', 'monitoring.index',
            'profile.*', 'logout', 'verification.*', 'password.*', 'confirm-password',
        ];

        $open = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('auth', $route->gatherMiddleware(), true)
                && ! in_array('role:admin', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->getName() ?? $route->uri())
            ->reject(fn (string $name) => Str::is($allowed, $name))
            ->values()
            ->all();

        $this->assertSame([], $open, 'Route ini terbuka untuk karyawan tetapi tidak ada di daftar yang diizinkan: '.implode(', ', $open));
    }

    public function test_store_scoped_models_protect_store_id_and_have_explicit_fillable(): void
    {
        $checked = 0;

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! in_array(BelongsToStore::class, class_uses_recursive($class), true)) {
                continue;
            }

            $model = new $class;
            $this->assertNotContains('store_id', $model->getFillable(), "{$class}: store_id tidak boleh mass-assignable");
            $this->assertNotEmpty($model->getFillable(), "{$class}: fillable harus eksplisit");
            $this->assertTrue(Schema::hasColumn($model->getTable(), 'store_id'), "{$class}: tabel {$model->getTable()} tanpa store_id");
            $checked++;
        }

        $this->assertGreaterThanOrEqual(11, $checked);
    }

    public function test_security_headers_are_sent(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('Strict-Transport-Security');

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_demo_seeder_creates_demo_accounts_outside_production(): void
    {
        (new DatabaseSeeder)->setContainer($this->app)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@stokara.test', 'role' => 'admin']);
        $this->assertDatabaseHas('users', ['email' => 'karyawan@stokara.test', 'role' => 'karyawan']);
        $this->assertDatabaseCount('products', 27);
    }

    public function test_demo_seeder_is_skipped_in_production(): void
    {
        $this->app['env'] = 'production';

        (new DatabaseSeeder)->setContainer($this->app)->run();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('stores', 0);
    }
}
