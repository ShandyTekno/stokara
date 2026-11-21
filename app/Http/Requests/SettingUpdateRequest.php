<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'theme_color' => ['required', 'string', Rule::in(array_keys(Store::THEME_COLORS))],
            'sidebar_style' => ['required', 'string', Rule::in(array_keys(Store::SIDEBAR_STYLES))],
            'font_family' => ['required', 'string', Rule::in(array_keys(Store::FONT_FAMILIES))],
            'font_size' => ['required', 'string', Rule::in(array_keys(Store::FONT_SIZES))],
            'border_radius' => ['required', 'string', Rule::in(array_keys(Store::BORDER_RADII))],
            'compact_mode' => ['nullable', 'boolean'],
            'receipt_footer' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'app_name' => 'nama website',
            'name' => 'nama toko',
            'tagline' => 'tagline',
            'phone' => 'nomor telepon',
            'address' => 'alamat toko',
            'logo' => 'logo website',
            'theme_color' => 'warna tema',
            'sidebar_style' => 'gaya sidebar',
            'font_family' => 'jenis font',
            'font_size' => 'ukuran font',
            'border_radius' => 'bentuk sudut',
            'receipt_footer' => 'catatan struk',
        ];
    }
}
