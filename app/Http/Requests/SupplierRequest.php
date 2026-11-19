<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'company_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama pemasok',
            'phone' => 'nomor telepon',
            'address' => 'alamat',
            'company_name' => 'nama perusahaan',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang (maksimal :max karakter).',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, +, -, dan tanda kurung.',
        ];
    }
}
