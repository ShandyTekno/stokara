<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_stats_for_own_store_only(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        Product::factory()->count(2)->create(['store_id' => $store->id, 'stock' => 50, 'min_stock' => 10]);
        Product::factory()->menipis()->create(['store_id' => $store->id]); // stok 5
        Product::factory()->habis()->create(['store_id' => $store->id]);   // stok 0
        Product::factory()->create(['stock' => 999]);                      // toko lain

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.total_products', 4)
                ->where('stats.total_stock', 105)
                ->where('stats.low_stock_count', 1)
                ->where('stats.out_of_stock_count', 1)
                ->where('stats.today_transactions', 0)
                ->has('lowStock', 2)
                ->has('salesChart.labels', 7)
                ->has('movementChart.masuk', 7));
    }

    public function test_dashboard_counts_only_todays_sales(): void
    {
        $store = Store::factory()->create();
        $user = User::factory()->create(['store_id' => $store->id]);

        $row = fn ($invoice, $when, $total) => [
            'store_id' => $store->id,
            'invoice_number' => $invoice,
            'sold_at' => $when,
            'total_amount' => $total,
            'payment_method' => 'tunai',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('sales')->insert([
            $row('INV-1', now(), 50000),
            $row('INV-2', now()->subDay(), 30000),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.today_transactions', 1)
                ->where('stats.today_sales', fn ($v) => (float) $v === 50000.0));
    }

    public function test_karyawan_can_open_dashboard(): void
    {
        $this->actingAs(User::factory()->karyawan()->create())
            ->get(route('dashboard'))
            ->assertOk();
    }
}
