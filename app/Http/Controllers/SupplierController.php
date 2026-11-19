<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\StockIn;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->withCount('products')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('company_name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
            ))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'company_name' => $s->company_name,
                'phone' => $s->phone,
                'products_count' => $s->products_count,
            ]);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($request->validated());

        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil ditambahkan.');
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $history = $supplier->stockIns()
            ->withCount('details')
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (StockIn $s) => [
                'id' => $s->id,
                'transaction_number' => $s->transaction_number,
                'date' => $s->transaction_date->format('d/m/Y'),
                'items' => $s->details_count,
                'total_cost' => (float) $s->total_cost,
                'notes' => $s->notes,
            ]);

        $lastDate = $supplier->stockIns()->max('transaction_date');

        return Inertia::render('Suppliers/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
            ],
            'tab' => $request->input('tab') === 'pengadaan' ? 'pengadaan' : 'info',
            'summary' => [
                'total_transactions' => $supplier->stockIns()->count(),
                'total_spent' => (float) $supplier->stockIns()->sum('total_cost'),
                'last_date' => $lastDate ? Carbon::parse($lastDate)->format('d/m/Y') : null,
            ],
            'products' => $supplier->products()
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'stock', 'unit', 'min_stock'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'stock' => $p->stock,
                    'unit' => $p->unit,
                    'status' => $p->stock_status->value,
                ]),
            'history' => $history,
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier->only(['id', 'name', 'phone', 'address', 'company_name']),
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->stockIns()->exists()) {
            return back()->with('error', 'Pemasok tidak dapat dihapus karena memiliki riwayat pengadaan.');
        }

        $supplier->delete(); // barang pemasok ini menjadi "tanpa pemasok"

        return redirect()->route('suppliers.index')->with('success', 'Pemasok berhasil dihapus.');
    }
}
