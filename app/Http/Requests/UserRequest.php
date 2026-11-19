<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        // Hanya admin; akun admin tidak bisa diubah lewat halaman ini.
        return $actor?->isAdmin() && (! $target || ! $target->isAdmin());
    }

    public function rules(): array
    {
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class, 'email')->ignore($target?->id),
            ],
            // Wajib saat membuat akun; saat mengubah, kosong = kata sandi tidak diganti.
            'password' => [$target ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email', 'password' => 'kata sandi'];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'email' => 'Format email tidak valid.',
            'lowercase' => 'Email harus ditulis dengan huruf kecil.',
            'email.unique' => 'Email sudah terdaftar.',
            'confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'max' => ':attribute terlalu panjang.',
        ];
    }
}
