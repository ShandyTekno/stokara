<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/pengguna')
            ->assertOk();
    }

    public function test_karyawan_is_forbidden_from_admin_page(): void
    {
        $this->actingAs(User::factory()->karyawan()->create())
            ->get('/pengguna')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengguna')->assertRedirect('/login');
    }

    public function test_karyawan_can_still_open_dashboard(): void
    {
        $this->actingAs(User::factory()->karyawan()->create())
            ->get('/dashboard')
            ->assertOk();
    }
}
