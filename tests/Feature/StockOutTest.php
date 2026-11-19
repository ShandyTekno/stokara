<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\StockOutReason;
use App\Enums\StockStatus;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockOut;
use App\Models\Store;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StockOutTest extends TestCase
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
        $this->product = Product::factory()->create([
            'store_id' => $this->store->id,
            'stock' => 10,
            'unit' => 'pcs',
            'min_stock' => 3,
        ]);
    }

    private function payload(?array $items = null, array $override = []): array
    {
        return array_merge([
            'transaction_date' => today()->toDateString(),
            'reason' => 'rusak',
            'notes' => 'Kemasan sobek',
            'items' => $items ?? [['product_id' => $this->product->id, 'quantity' => 4]],
        ], $override);
    }

    public function test_stock_out_reduces_stock_and_records_movement(): void
    {
        $response = $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload());

        $stockOut = StockOut::firstOrFail();
        $response->assertRedirect(route('stock-outs.show', $stockOut));

        $this->assertMatchesRegularExpression('/^SO-\d{8}-0001$/', $stockOut->transaction_number);
        $this->assertSame(StockOutReason::Rusak, $stockOut->reason);
        $this->assertEquals($this->store->id, $stockOut->store_id);
        $this->assertEquals(4, $stockOut->details()->firstOrFail()->quantity);
        $this->assertSame(6, $this->product->fresh()->stock);

        $movement = StockMovement::where('product_id', $this->product->id)->firstOrFail();
        $this->assertSame(StockMovementType::StockOut, $movement->type);
        $this->assertSame(-4, $movement->quantity_change);
        $this->assertSame(10, $movement->stock_before);
        $this->assertSame(6, $movement->stock_after);
        $this->assertEquals($stockOut->id, $movement->reference_id);
        $this->assertEquals($this->admin->id, $movement->user_id);
    }

    public function test_stock_can_be_reduced_to_exactly_zero(): void
    {
        $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 10],
        ]))->assertSessionHasNoErrors();

        $product = $this->product->fresh();
        $this->assertSame(0, $product->stock);
        $this->assertSame(StockStatus::Habis, $product->stock_status);
    }

    public function test_quantity_above_stock_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 11],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseCount('stock_out_details', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_failure_on_one_line_rolls_back_the_whole_transaction(): void
    {
        $other = Product::factory()->create(['store_id' => $this->store->id, 'stock' => 5]);

        $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 4],
            ['product_id' => $other->id, 'quantity' => 999],
        ]))->assertSessionHasErrors('items.1.quantity');

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(5, $other->fresh()->stock);
        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_service_never_lets_duplicate_lines_make_stock_negative(): void
    {
        try {
            app(StockService::class)->recordStockOut([
                'transaction_date' => today()->toDateString(),
                'reason' => 'hilang',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 6],
                    ['product_id' => $this->product->id, 'quantity' => 6],
                ],
            ], $this->admin);

            $this->fail('ValidationException seharusnya dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.1.quantity', $e->errors());
        }

        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_notes_are_required_for_reason_lainnya(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('stock-outs.store'), $this->payload(null, ['reason' => 'lainnya', 'notes' => '']))
            ->assertSessionHasErrors('notes');

        $this->post(route('stock-outs.store'), $this->payload(null, ['reason' => 'lainnya', 'notes' => 'Dipakai sendiri']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stock_outs', 1);
    }

    public function test_notes_are_optional_for_other_reasons(): void
    {
        $this->actingAs($this->admin)
            ->post(route('stock-outs.store'), $this->payload(null, ['notes' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('stock_outs', 1);
    }

    public function test_validation_rules(): void
    {
        $foreign = Product::factory()->create(); // toko lain
        $this->actingAs($this->admin);

        $this->post(route('stock-outs.store'), $this->payload(null, ['reason' => 'dicuri']))
            ->assertSessionHasErrors('reason');

        $this->post(route('stock-outs.store'), $this->payload([]))
            ->assertSessionHasErrors('items');

        $this->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 0],
        ]))->assertSessionHasErrors('items.0.quantity');

        $this->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]))->assertSessionHasErrors('items.1.product_id');

        $this->post(route('stock-outs.store'), $this->payload([
            ['product_id' => $foreign->id, 'quantity' => 1],
        ]))->assertSessionHasErrors('items.0.product_id');

        $this->post(route('stock-outs.store'), $this->payload(null, ['transaction_date' => today()->addDay()->toDateString()]))
            ->assertSessionHasErrors('transaction_date');

        $this->assertDatabaseCount('stock_outs', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_karyawan_can_record_stock_out(): void
    {
        $this->actingAs($this->karyawan)
            ->post(route('stock-outs.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame(6, $this->product->fresh()->stock);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('stock-outs.index'))->assertRedirect('/login');
        $this->post(route('stock-outs.store'), $this->payload())->assertRedirect('/login');
    }

    public function test_stock_out_from_other_store_is_not_accessible(): void
    {
        $other = Store::factory()->create();
        $foreign = new StockOut([
            'transaction_number' => 'SO-20260101-0001',
            'transaction_date' => today()->toDateString(),
            'reason' => StockOutReason::Rusak,
        ]);
        $foreign->store_id = $other->id;
        $foreign->save();

        $this->actingAs($this->admin)
            ->get(route('stock-outs.show', $foreign->id))
            ->assertNotFound();
    }

    public function test_show_and_index_display_data(): void
    {
        $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload());
        $stockOut = StockOut::firstOrFail();

        $this->get(route('stock-outs.show', $stockOut))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockOuts/Show')
                ->where('stockOut.reason', 'Rusak')
                ->has('stockOut.details', 1)
                ->where('stockOut.details.0.quantity', 4));

        $this->get(route('stock-outs.index', ['search' => $stockOut->transaction_number]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('StockOuts/Index')
                ->has('stockOuts.data', 1)
                ->where('stockOuts.data.0.total_units', 4));

        $this->get(route('stock-outs.index', ['to' => today()->subDay()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('stockOuts.data', 0));
    }

    public function test_dashboard_chart_counts_stock_out(): void
    {
        $this->actingAs($this->admin)->post(route('stock-outs.store'), $this->payload());

        $this->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('movementChart.keluar.6', 4));
    }
}
