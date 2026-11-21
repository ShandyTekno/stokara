<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingUpdateRequest;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function edit(Request $request): Response
    {
        $store = $request->user()->store;

        return Inertia::render('Settings/Edit', [
            'store' => [
                'app_name' => $store->app_name ?: config('app.name', 'STOKARA'),
                'name' => $store->name,
                'tagline' => $store->tagline ?: 'Sistem Manajemen Stok & Kasir Modern',
                'phone' => $store->phone ?? '',
                'address' => $store->address ?? '',
                'logo_url' => $store->logo_url,
                'settings' => $store->resolvedSettings(),
            ],
            'options' => [
                'theme_colors' => Store::THEME_COLORS,
                'sidebar_styles' => Store::SIDEBAR_STYLES,
                'font_families' => Store::FONT_FAMILIES,
                'font_sizes' => Store::FONT_SIZES,
                'border_radii' => Store::BORDER_RADII,
            ],
        ]);
    }

    public function update(SettingUpdateRequest $request): RedirectResponse
    {
        $store = $request->user()->store;
        $data = $request->validated();

        $logoPath = $store->logo;

        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        $store->update([
            'app_name' => $data['app_name'],
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'logo' => $logoPath,
            'settings' => [
                'theme_color' => $data['theme_color'],
                'sidebar_style' => $data['sidebar_style'],
                'font_family' => $data['font_family'],
                'font_size' => $data['font_size'],
                'border_radius' => $data['border_radius'],
                'compact_mode' => $request->boolean('compact_mode'),
                'receipt_footer' => $data['receipt_footer'] ?? '',
            ],
        ]);

        return redirect()->route('settings.edit')
            ->with('success', 'Pengaturan website berhasil disimpan.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $store = $request->user()->store;

        $store->update([
            'settings' => Store::DEFAULT_SETTINGS,
        ]);

        return redirect()->route('settings.edit')
            ->with('success', 'Tampilan & tema berhasil dikembalikan ke pengaturan awal.');
    }
}
