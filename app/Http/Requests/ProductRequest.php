<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (empty($this->code) && ! empty($this->name)) {
            $this->merge([
                'code' => app(\App\Services\ProductService::class)->generateCode(
                    (string) $this->name,
                    $this->user()->store_id,
                    $this->route('product')?->id
                ),
            ]);
        }
    }

    public function rules(): array
    {
        $storeId = $this->user()->store_id;
        $product = $this->route('product');

        $rules = [
            'code' => [
                'required', 'string', 'max:50',
                // Unik per toko, termasuk barang yang sudah dihapus (sesuai indeks database).
                Rule::unique('products', 'code')->where('store_id', $storeId)->ignore($product?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('store_id', $storeId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('store_id', $storeId)],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'unit' => ['required', 'string', 'max:20'],
            'pack_unit' => ['nullable', 'string', 'max:20'],
            'units_per_pack' => ['required', 'integer', 'min:1', 'max:100000'],
            'min_stock' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];

        // Stok awal hanya saat membuat barang; selanjutnya stok berubah lewat transaksi.
        if (! $product) {
            $rules['initial_stock'] = ['required', 'integer', 'min:0', 'max:1000000000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode barang',
            'name' => 'nama barang',
            'category_id' => 'kategori',
            'supplier_id' => 'pemasok',
            'purchase_price' => 'harga beli',
            'selling_price' => 'harga jual',
            'unit' => 'satuan',
            'pack_unit' => 'satuan kemasan',
            'units_per_pack' => 'isi per kemasan',
            'min_stock' => 'stok minimum',
            'initial_stock' => 'stok awal',
            'photo' => 'foto',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'numeric' => ':attribute harus berupa angka.',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'max' => ':attribute melebihi batas maksimum (:max).',
            'code.unique' => 'Kode barang sudah dipakai di toko ini (termasuk barang yang sudah dihapus).',
            'category_id.exists' => 'Kategori tidak valid.',
            'supplier_id.exists' => 'Pemasok tidak valid.',
            'photo.image' => 'Foto harus berupa gambar.',
            'photo.mimes' => 'Foto harus berformat JPG, PNG, atau WEBP.',
            'photo.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }
}
