<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->withCount('products')
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'products_count' => $c->products_count,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Barang di kategori ini menjadi "tanpa kategori" (FK nullOnDelete).
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Kategori berhasil dihapus.');
    }
}
