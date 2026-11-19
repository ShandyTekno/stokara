<?php

namespace App\Http\Controllers;

use App\Http\Requests\StockInRequest;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\Supplier;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockInController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $search = $filters['search'] ?? null;
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = StockIn::query()
            ->when($search, fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('transaction_number', 'like', "%{$s}%")
                ->orWhereHas('supplier', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($from, fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d));

        $stockIns = (clone $base)
            ->with(['supplier:id,name', 'user:id,name'])
            ->withCount('details')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (StockIn $s) => [
                'id' => $s->id,
                'transaction_number' => $s->transaction_number,
                'date' => $s->transaction_date->format('d/m/Y'),
                'supplier' => $s->supplier?->name,
                'items' => $s->details_count,
                'total_cost' => (float) $s->total_cost,
                'user' => $s->user?->name,
            ]);

        return Inertia::render('StockIns/Index', [
            'stockIns' => $stockIns,
            'summary' => [
                'count' => (clone $base)->count(),
                'total_cost' => (float) (clone $base)->sum('total_cost'),
            ],
            'filters' => [
                'search' => $request->input('search', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('StockIns/Create', [
            'products' => Product::orderBy('name')
                ->get(['id', 'code', 'name', 'unit', 'pack_unit', 'units_per_pack', 'stock', 'purchase_price'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'unit' => $p->unit,
                    'pack_unit' => $p->pack_unit,
                    'units_per_pack' => $p->units_per_pack,
                    'stock' => $p->stock,
                    'purchase_price' => (float) $p->purchase_price,
                ]),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StockInRequest $request, StockService $stock): RedirectResponse
    {
        $stockIn = $stock->recordStockIn($request->validated(), $request->user());

        return redirect()
            ->route('stock-ins.show', $stockIn)
            ->with('success', "Barang masuk {$stockIn->transaction_number} berhasil dicatat.");
    }

    public function show(StockIn $stockIn): Response
    {
        $stockIn->load(['supplier:id,name,phone', 'user:id,name', 'details.product']);

        return Inertia::render('StockIns/Show', [
            'stockIn' => [
                'id' => $stockIn->id,
                'transaction_number' => $stockIn->transaction_number,
                'date' => $stockIn->transaction_date->format('d/m/Y'),
                'supplier' => $stockIn->supplier?->name,
                'user' => $stockIn->user?->name,
                'total_cost' => (float) $stockIn->total_cost,
                'notes' => $stockIn->notes,
                'created_at' => $stockIn->created_at->format('d/m/Y H:i'),
                'details' => $stockIn->details->map(fn ($d) => [
                    'id' => $d->id,
                    'product_id' => $d->product_id,
                    'product' => $d->product?->name,
                    'code' => $d->product?->code,
                    'unit' => $d->product?->unit,
                    'pack_unit' => $d->product?->pack_unit,
                    'product_deleted' => (bool) $d->product?->trashed(),
                    'input_quantity' => (int) $d->input_quantity,
                    'is_package' => (bool) $d->is_package,
                    'units_per_pack' => (int) $d->units_per_pack,
                    'quantity' => (int) $d->quantity,
                    'unit_cost' => (float) $d->unit_cost,
                    'subtotal' => (float) $d->subtotal,
                ])->values(),
            ],
        ]);
    }
}
