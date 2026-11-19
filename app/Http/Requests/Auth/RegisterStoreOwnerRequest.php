<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterStoreOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_name' => 'nama toko',
            'name' => 'nama lengkap',
            'email' => 'email',
            'password' => 'kata sandi',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email' => 'Format email tidak valid.',
            'unique' => ':attribute sudah terdaftar.',
            'confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'max' => ':attribute terlalu panjang.',
        ];
    }
}
