<?php

namespace App\Http\Controllers;

use App\Enums\StockOutReason;
use App\Http\Requests\StockOutRequest;
use App\Models\Product;
use App\Models\StockOut;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockOutController extends Controller
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

        $base = StockOut::query()
            ->when($search, fn ($q, $s) => $q->where('transaction_number', 'like', "%{$s}%"))
            ->when($from, fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d));

        $stockOuts = (clone $base)
            ->with('user:id,name')
            ->withCount('details')
            ->withSum('details', 'quantity')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (StockOut $s) => [
                'id' => $s->id,
                'transaction_number' => $s->transaction_number,
                'date' => $s->transaction_date->format('d/m/Y'),
                'reason' => $s->reason->label(),
                'items' => $s->details_count,
                'total_units' => (int) $s->details_sum_quantity,
                'user' => $s->user?->name,
            ]);

        return Inertia::render('StockOuts/Index', [
            'stockOuts' => $stockOuts,
            'summary' => ['count' => (clone $base)->count()],
            'filters' => [
                'search' => $request->input('search', ''),
                'from' => $request->input('from', ''),
                'to' => $request->input('to', ''),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('StockOuts/Create', [
            'products' => Product::orderBy('name')
                ->get(['id', 'code', 'name', 'unit', 'stock'])
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'unit' => $p->unit,
                    'stock' => $p->stock,
                ]),
            'reasons' => collect(StockOutReason::cases())
                ->map(fn (StockOutReason $r) => ['value' => $r->value, 'label' => $r->label()])
                ->values(),
            'today' => today()->toDateString(),
        ]);
    }

    public function store(StockOutRequest $request, StockService $stock): RedirectResponse
    {
        $stockOut = $stock->recordStockOut($request->validated(), $request->user());

        return redirect()
            ->route('stock-outs.show', $stockOut)
            ->with('success', "Barang keluar {$stockOut->transaction_number} berhasil dicatat.");
    }

    public function show(StockOut $stockOut): Response
    {
        $stockOut->load(['user:id,name', 'details.product']);

        return Inertia::render('StockOuts/Show', [
            'stockOut' => [
                'id' => $stockOut->id,
                'transaction_number' => $stockOut->transaction_number,
                'date' => $stockOut->transaction_date->format('d/m/Y'),
                'reason' => $stockOut->reason->label(),
                'user' => $stockOut->user?->name,
                'notes' => $stockOut->notes,
                'created_at' => $stockOut->created_at->format('d/m/Y H:i'),
                'details' => $stockOut->details->map(fn ($d) => [
                    'id' => $d->id,
                    'product_id' => $d->product_id,
                    'product' => $d->product?->name,
                    'code' => $d->product?->code,
                    'unit' => $d->product?->unit,
                    'product_deleted' => (bool) $d->product?->trashed(),
                    'quantity' => (int) $d->quantity,
                ])->values(),
            ],
        ]);
    }
}
