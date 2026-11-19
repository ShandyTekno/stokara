<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->store_id !== null;
    }

    public function rules(): array
    {
        $storeId = $this->user()->store_id;

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('store_id', $storeId)],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('store_id', $storeId)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.is_package' => ['sometimes', 'boolean'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'transaction_date' => 'tanggal',
            'supplier_id' => 'pemasok',
            'notes' => 'keterangan',
            'items' => 'daftar barang',
            'items.*.product_id' => 'barang',
            'items.*.quantity' => 'jumlah',
            'items.*.is_package' => 'satuan',
            'items.*.price' => 'harga beli',
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
            'date' => 'Format tanggal tidak valid.',
            'boolean' => ':attribute tidak valid.',
            'exists' => ':attribute tidak valid.',
            'before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.min' => 'Tambahkan minimal satu barang.',
            'items.max' => 'Maksimal 100 barang per transaksi.',
            'items.*.product_id.distinct' => 'Barang yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
