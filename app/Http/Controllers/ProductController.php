<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with('category:id,name')
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', $request->integer('category')))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Product $p) => $this->summary($p));

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
            ],
            'can' => ['manage' => $request->user()->isAdmin()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Create', $this->formOptions());
    }

    public function store(ProductRequest $request, ProductService $products): RedirectResponse
    {
        $products->create($request->validated(), $request->file('photo'), $request->user());

        return redirect()->route('products.index')->with('success', 'Barang berhasil ditambahkan.');
    }

    public function show(Request $request, Product $product): Response
    {
        $product->load(['category:id,name', 'supplier:id,name']);

        $movements = $product->movements()
            ->with('user:id,name')
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type->label(),
                'change' => $m->quantity_change,
                'before' => $m->stock_before,
                'after' => $m->stock_after,
                'user' => $m->user?->name,
                'notes' => $m->notes,
                'time' => $m->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Products/Show', [
            'product' => $this->detail($product),
            'movements' => $movements,
            'can' => ['manage' => $request->user()->isAdmin()],
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Products/Edit', [
            'product' => $this->detail($product),
            ...$this->formOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductService $products): RedirectResponse
    {
        $products->update(
            $product,
            $request->validated(),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return redirect()->route('products.index')->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete(); // soft delete: riwayat transaksi tetap utuh

        return redirect()->route('products.index')->with('success', 'Barang berhasil dihapus.');
    }

    private function formOptions(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ];
    }

    private function summary(Product $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'category' => $p->category?->name,
            'stock' => $p->stock,
            'unit' => $p->unit,
            'selling_price' => (float) $p->selling_price,
            'status' => $p->stock_status->value,
            'photo_url' => $p->photo_url,
        ];
    }

    private function detail(Product $p): array
    {
        return [
            'id' => $p->id,
            'code' => $p->code,
            'name' => $p->name,
            'category_id' => $p->category_id,
            'category' => $p->category?->name,
            'supplier_id' => $p->supplier_id,
            'supplier' => $p->supplier?->name,
            'purchase_price' => (float) $p->purchase_price,
            'selling_price' => (float) $p->selling_price,
            'stock' => $p->stock,
            'unit' => $p->unit,
            'pack_unit' => $p->pack_unit,
            'units_per_pack' => $p->units_per_pack,
            'min_stock' => $p->min_stock,
            'status' => $p->stock_status->value,
            'photo_url' => $p->photo_url,
            'created_at' => $p->created_at->format('d/m/Y'),
        ];
    }
}
