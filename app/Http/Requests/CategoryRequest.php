<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->where('store_id', $this->user()->store_id)
                    ->ignore($this->route('category')?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama kategori', 'description' => 'deskripsi'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'max' => ':attribute terlalu panjang (maksimal :max karakter).',
            'name.unique' => 'Nama kategori sudah ada di toko ini.',
        ];
    }
}
