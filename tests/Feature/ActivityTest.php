<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Store;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
        $this->product = Product::factory()->create(['store_id' => $this->store->id]);
    }

    private function movement(User $by): StockMovement
    {
        return StockMovement::create([
            'product_id' => $this->product->id,
            'user_id' => $by->id,
            'type' => StockMovementType::StockIn,
            'quantity_change' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);
    }

    public function test_admin_sees_activities_of_own_store_only(): void
    {
        $this->actingAs($this->admin);
        $this->movement($this->admin);
        $this->movement($this->karyawan);

        $foreign = Product::factory()->create();
        DB::table('stock_movements')->insert([
            'store_id' => $foreign->store_id,
            'product_id' => $foreign->id,
            'type' => 'stock_in',
            'quantity_change' => 5,
            'stock_before' => 0,
            'stock_after' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('activities.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Activities/Index')
                ->has('activities.data', 2)
                ->has('summary', 2));
    }

    public function test_activities_can_be_filtered_by_user(): void
    {
        $this->actingAs($this->admin);
        $this->movement($this->admin);
        $this->movement($this->karyawan);

        $this->get(route('activities.index', ['user' => $this->karyawan->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.user', $this->karyawan->name));
    }

    public function test_activities_can_be_filtered_by_date(): void
    {
        $this->actingAs($this->admin);
        $old = $this->movement($this->admin);
        $this->movement($this->admin);

        DB::table('stock_movements')->where('id', $old->id)->update(['created_at' => now()->subDays(10)]);

        $this->get(route('activities.index', ['from' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));

        $this->get(route('activities.index', ['to' => today()->subDays(5)->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('activities.data', 1));
    }

    public function test_karyawan_is_forbidden(): void
    {
        $this->actingAs($this->karyawan)->get(route('activities.index'))->assertForbidden();
    }
}
