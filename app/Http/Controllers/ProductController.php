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
        $perPage = in_array($request->integer('per_page'), [10, 25, 50, 100], true)
            ? $request->integer('per_page')
            : 10;

        $products = Product::query()
            ->select('products.*')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->with('category:id,name')
            ->search($request->string('search')->toString())
            ->when($request->filled('category'), fn ($q) => $q->where('products.category_id', $request->integer('category')))
            ->orderByRaw("CASE 
                WHEN categories.name LIKE '%Beras%' OR categories.name LIKE '%Pokok%' THEN 1
                WHEN categories.name LIKE '%Minyak%' OR categories.name LIKE '%Bumbu%' THEN 2
                WHEN categories.name LIKE '%Ikan%' OR categories.name LIKE '%Ayam%' OR categories.name LIKE '%Daging%' THEN 3
                WHEN categories.name LIKE '%Sayur%' OR categories.name LIKE '%Buah%' THEN 4
                WHEN categories.name LIKE '%Beku%' OR categories.name LIKE '%Frozen%' THEN 5
                WHEN categories.name LIKE '%Makanan%' OR categories.name LIKE '%Mie%' OR categories.name LIKE '%Minuman%' THEN 6
                WHEN categories.name LIKE '%Jajanan%' OR categories.name LIKE '%Roti%' OR categories.name LIKE '%Camilan%' THEN 7
                WHEN categories.name LIKE '%Bayi%' OR categories.name LIKE '%Kesehatan%' THEN 8
                WHEN categories.name LIKE '%Rumah Tangga%' OR categories.name LIKE '%Sabun%' THEN 9
                WHEN categories.name LIKE '%Gas%' OR categories.name LIKE '%Galon%' OR categories.name LIKE '%Lainnya%' THEN 10
                ELSE 11
            END ASC")
            ->orderBy('categories.name', 'ASC')
            ->orderBy('products.name', 'ASC')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Product $p) => $this->summary($p));

        $categories = Category::query()
            ->orderByRaw("CASE 
                WHEN name LIKE '%Beras%' OR name LIKE '%Pokok%' THEN 1
                WHEN name LIKE '%Minyak%' OR name LIKE '%Bumbu%' THEN 2
                WHEN name LIKE '%Ikan%' OR name LIKE '%Ayam%' OR name LIKE '%Daging%' THEN 3
                WHEN name LIKE '%Sayur%' OR name LIKE '%Buah%' THEN 4
                WHEN name LIKE '%Beku%' OR name LIKE '%Frozen%' THEN 5
                WHEN name LIKE '%Makanan%' OR name LIKE '%Mie%' OR name LIKE '%Minuman%' THEN 6
                WHEN name LIKE '%Jajanan%' OR name LIKE '%Roti%' OR name LIKE '%Camilan%' THEN 7
                WHEN name LIKE '%Bayi%' OR name LIKE '%Kesehatan%' THEN 8
                WHEN name LIKE '%Rumah Tangga%' OR name LIKE '%Sabun%' THEN 9
                WHEN name LIKE '%Gas%' OR name LIKE '%Galon%' OR name LIKE '%Lainnya%' THEN 10
                ELSE 11
            END ASC")
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => [
                'search' => $request->input('search', ''),
                'category' => $request->input('category', ''),
                'per_page' => $perPage,
            ],
            'can' => ['manage' => $request->user()->isAdmin()],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Products/Create', $this->formOptions());
    }

    public function generateCode(Request $request, ProductService $products): \Illuminate\Http\JsonResponse
    {
        $name = $request->string('name')->trim()->toString();
        if ($name === '') {
            return response()->json(['code' => '']);
        }

        $code = $products->generateCode($name, $request->user()->store_id);

        return response()->json(['code' => $code]);
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
            'categories' => Category::query()
                ->orderByRaw("CASE 
                    WHEN name LIKE '%Beras%' OR name LIKE '%Pokok%' THEN 1
                    WHEN name LIKE '%Minyak%' OR name LIKE '%Bumbu%' THEN 2
                    WHEN name LIKE '%Ikan%' OR name LIKE '%Ayam%' OR name LIKE '%Daging%' THEN 3
                    WHEN name LIKE '%Sayur%' OR name LIKE '%Buah%' THEN 4
                    WHEN name LIKE '%Beku%' OR name LIKE '%Frozen%' THEN 5
                    WHEN name LIKE '%Makanan%' OR name LIKE '%Mie%' OR name LIKE '%Minuman%' THEN 6
                    WHEN name LIKE '%Jajanan%' OR name LIKE '%Roti%' OR name LIKE '%Camilan%' THEN 7
                    WHEN name LIKE '%Bayi%' OR name LIKE '%Kesehatan%' THEN 8
                    WHEN name LIKE '%Rumah Tangga%' OR name LIKE '%Sabun%' THEN 9
                    WHEN name LIKE '%Gas%' OR name LIKE '%Galon%' OR name LIKE '%Lainnya%' THEN 10
                    ELSE 11
                END ASC")
                ->orderBy('name')
                ->get(['id', 'name']),
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
