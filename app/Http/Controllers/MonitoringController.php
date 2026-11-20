<?php

namespace App\Http\Controllers;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['stok', 'riwayat'])],
            'search' => ['nullable', 'string', 'max:100'],
            'category' => [
                'nullable', 'integer',
                Rule::exists('categories', 'id')->where('store_id', $request->user()->store_id),
            ],
            'status' => ['nullable', Rule::in(['aman', 'menipis', 'habis'])],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $tab = ($filters['tab'] ?? 'stok') === 'riwayat' ? 'riwayat' : 'stok';

        $row = Product::query()->selectRaw(
            'COUNT(*) as total,
             SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as habis,
             SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) as menipis'
        )->first();

        $total = (int) $row->total;
        $habis = (int) $row->habis;
        $menipis = (int) $row->menipis;

        return Inertia::render('Monitoring/Index', [
            'tab' => $tab,
            'counts' => [
                'total' => $total,
                'aman' => $total - $habis - $menipis,
                'menipis' => $menipis,
                'habis' => $habis,
            ],
            'products' => $tab === 'stok' ? $this->products($filters) : null,
            'movements' => $tab === 'riwayat' ? $this->movements($filters) : null,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'types' => collect(StockMovementType::cases())
                ->map(fn (StockMovementType $t) => ['value' => $t->value, 'label' => $t->label()])
                ->values(),
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
                'status' => $request->input('status', ''),
                'type' => $request->input('type', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
        ]);
    }

    private function products(array $filters)
    {
        return Product::query()
            ->with('category:id,name')
            ->search($filters['search'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->filterStatus($filters['status'] ?? null)
            ->orderByRaw('CASE WHEN stock <= 0 THEN 0 WHEN stock <= min_stock THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'category' => $p->category?->name,
                'stock' => $p->stock,
                'min_stock' => $p->min_stock,
                'gap' => $p->stock - $p->min_stock,
                'unit' => $p->unit,
                'status' => $p->stock_status->value,
            ]);
    }

    private function movements(array $filters)
    {
        return StockMovement::query()
            ->with(['product:id,name,code,unit', 'user:id,name'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->whereHas(
                'product',
                fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (StockMovement $m) => [
                'id' => $m->id,
                'time' => $m->created_at->format('d/m/Y H:i'),
                'product' => $m->product?->name,
                'code' => $m->product?->code,
                'unit' => $m->product?->unit,
                'type' => $m->type->label(),
                'change' => $m->quantity_change,
                'before' => $m->stock_before,
                'after' => $m->stock_after,
                'user' => $m->user?->name ?? 'Sistem',
                'notes' => $m->notes,
            ]);
    }
}
