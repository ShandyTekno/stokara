<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $admin;

    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['name' => 'Toko Sembako Awal']);
        $this->admin = User::factory()->create([
            'store_id' => $this->store->id,
            'role' => Role::Admin,
        ]);
        $this->karyawan = User::factory()->create([
            'store_id' => $this->store->id,
            'role' => Role::Karyawan,
        ]);
    }

    public function test_admin_can_view_settings_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Edit')
                ->where('store.name', 'Toko Sembako Awal')
                ->has('options.theme_colors')
                ->has('options.font_families')
            );
    }

    public function test_karyawan_cannot_access_settings_page(): void
    {
        $this->actingAs($this->karyawan)
            ->get(route('settings.edit'))
            ->assertForbidden();
    }

    public function test_admin_can_update_identity_logo_and_appearance(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('settings.update'), [
            'app_name' => 'WARUNG JAYA',
            'name' => 'Toko Sembako Jaya',
            'tagline' => 'Belanja Hemat Setiap Hari',
            'phone' => '08123456789',
            'address' => 'Jl. Merdeka No. 10',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'theme_color' => 'emerald',
            'sidebar_style' => 'colored',
            'font_family' => 'poppins',
            'font_size' => 'lg',
            'border_radius' => 'pill',
            'compact_mode' => true,
            'receipt_footer' => 'Sampai jumpa kembali!',
        ]);

        $response->assertRedirect(route('settings.edit'));

        $this->store->refresh();
        $this->assertSame('WARUNG JAYA', $this->store->app_name);
        $this->assertSame('Toko Sembako Jaya', $this->store->name);
        $this->assertSame('Belanja Hemat Setiap Hari', $this->store->tagline);
        $this->assertNotNull($this->store->logo);
        Storage::disk('public')->assertExists($this->store->logo);

        $settings = $this->store->resolvedSettings();
        $this->assertSame('emerald', $settings['theme_color']);
        $this->assertSame('colored', $settings['sidebar_style']);
        $this->assertSame('poppins', $settings['font_family']);
        $this->assertSame('lg', $settings['font_size']);
        $this->assertSame('pill', $settings['border_radius']);
        $this->assertTrue($settings['compact_mode']);
        $this->assertSame('Sampai jumpa kembali!', $settings['receipt_footer']);
    }

    public function test_invalid_theme_or_font_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('settings.update'), [
                'app_name' => 'STOKARA',
                'name' => 'Toko',
                'theme_color' => 'neon-invalid',
                'sidebar_style' => 'dark',
                'font_family' => 'comic-sans-invalid',
                'font_size' => 'md',
                'border_radius' => 'rounded',
            ])
            ->assertSessionHasErrors(['theme_color', 'font_family']);
    }

    public function test_admin_can_reset_appearance_to_defaults(): void
    {
        $this->store->update([
            'app_name' => 'TOKO CUSTOM',
            'settings' => [
                'theme_color' => 'rose',
                'sidebar_style' => 'light',
                'font_family' => 'roboto',
                'font_size' => 'lg',
                'border_radius' => 'sharp',
                'compact_mode' => true,
            ],
        ]);

        $this->actingAs($this->admin)
            ->post(route('settings.reset'))
            ->assertRedirect(route('settings.edit'));

        $this->store->refresh();
        $this->assertSame('TOKO CUSTOM', $this->store->app_name);
        $this->assertSame(Store::DEFAULT_SETTINGS, $this->store->resolvedSettings());
    }
}
