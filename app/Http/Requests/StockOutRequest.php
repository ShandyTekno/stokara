<?php

namespace App\Http\Requests;

use App\Enums\StockOutReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOutRequest extends FormRequest
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
            'reason' => ['required', Rule::enum(StockOutReason::class)],
            // Alasan "lainnya" wajib dijelaskan.
            'notes' => ['nullable', Rule::requiredIf($this->input('reason') === StockOutReason::Lainnya->value), 'string', 'max:500'],
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
            'transaction_date' => 'tanggal',
            'reason' => 'alasan pengeluaran',
            'notes' => 'keterangan',
            'items' => 'daftar barang',
            'items.*.product_id' => 'barang',
            'items.*.quantity' => 'jumlah',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'max' => ':attribute melebihi batas maksimum (:max).',
            'date' => 'Format tanggal tidak valid.',
            'exists' => ':attribute tidak valid.',
            'enum' => 'Alasan pengeluaran tidak valid.',
            'before_or_equal' => 'Tanggal tidak boleh melewati hari ini.',
            'notes.required' => 'Keterangan wajib diisi untuk alasan “Lainnya”.',
            'items.required' => 'Tambahkan minimal satu barang.',
            'items.min' => 'Tambahkan minimal satu barang.',
            'items.max' => 'Maksimal 100 barang per transaksi.',
            'items.*.product_id.distinct' => 'Barang yang sama tidak boleh dipilih dua kali.',
        ];
    }
}
