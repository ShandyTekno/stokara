<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->store_id !== null;
    }

    public function rules(): array
    {
        $storeId = $this->user()->store_id;

        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount_paid' => [
                'nullable',
                Rule::requiredIf($this->input('payment_method') === PaymentMethod::Tunai->value),
                'numeric', 'min:0', 'max:9999999999999',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('products', 'id')->where('store_id', $storeId)->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_method' => 'metode pembayaran',
            'amount_paid' => 'uang diterima',
            'notes' => 'catatan',
            'items' => 'keranjang',
            'items.*.product_id' => 'barang',
            'items.*.quantity' => 'jumlah',
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
            'exists' => ':attribute tidak valid.',
            'enum' => 'Metode pembayaran tidak valid.',
            'amount_paid.required' => 'Uang diterima wajib diisi untuk pembayaran tunai.',
            'items.required' => 'Keranjang masih kosong.',
            'items.min' => 'Keranjang masih kosong.',
            'items.max' => 'Maksimal 100 jenis barang per transaksi.',
            'items.*.product_id.distinct' => 'Barang yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
