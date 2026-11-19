<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;
    private User $admin;
    private User $karyawan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create();
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $override);
    }

    public function test_index_lists_only_users_from_own_store(): void
    {
        User::factory()->karyawan()->create(['store_id' => $this->store->id, 'name' => 'Siti']);
        User::factory()->karyawan()->create(['name' => 'Orang Toko Lain']);

        $this->actingAs($this->admin)->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Users/Index')
                ->has('users.data', 3)); // admin, karyawan (setUp), Siti
    }

    public function test_admin_creates_karyawan_with_role_and_store_set_by_server(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->payload(['role' => 'admin', 'store_id' => 999]))
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(Role::Karyawan, $user->role);
        $this->assertSame($this->store->id, $user->store_id);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_creation_is_validated(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('users.store'), $this->payload(['email' => $this->karyawan->email]))
            ->assertSessionHasErrors('email');
        $this->post(route('users.store'), $this->payload(['password_confirmation' => 'beda']))
            ->assertSessionHasErrors('password');
        $this->post(route('users.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_karyawan_is_forbidden_from_user_management(): void
    {
        $staff = User::factory()->karyawan()->create(['store_id' => $this->store->id]);
        $this->actingAs($this->karyawan);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('users.create'))->assertForbidden();
        $this->post(route('users.store'), $this->payload())->assertForbidden();
        $this->get(route('users.edit', $staff))->assertForbidden();
        $this->patch(route('users.toggle', $staff))->assertForbidden();
    }

    public function test_users_from_other_stores_are_not_accessible(): void
    {
        $foreign = User::factory()->karyawan()->create(); // toko lain
        $this->actingAs($this->admin);

        $this->get(route('users.edit', $foreign))->assertNotFound();
        $this->put(route('users.update', $foreign), $this->payload())->assertNotFound();
        $this->patch(route('users.toggle', $foreign))->assertNotFound();
        $this->assertTrue($foreign->fresh()->is_active);
    }

    public function test_admin_accounts_cannot_be_edited_or_deactivated_here(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('users.edit', $this->admin))->assertForbidden();
        $this->put(route('users.update', $this->admin), $this->payload())->assertForbidden();
        $this->patch(route('users.toggle', $this->admin))->assertForbidden();
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_update_changes_profile_and_resets_password_only_when_filled(): void
    {
        $this->actingAs($this->admin);

        $this->put(route('users.update', $this->karyawan), [
            'name' => 'Nama Baru',
            'email' => $this->karyawan->email,
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect(route('users.index'));

        $staff = $this->karyawan->fresh();
        $this->assertSame('Nama Baru', $staff->name);
        $this->assertTrue(Hash::check('password', $staff->password));

        $this->put(route('users.update', $this->karyawan), [
            'name' => 'Nama Baru',
            'email' => $this->karyawan->email,
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('rahasia123', $this->karyawan->fresh()->password));
    }

    public function test_admin_can_deactivate_and_reactivate_karyawan(): void
    {
        $this->actingAs($this->admin);

        $this->patch(route('users.toggle', $this->karyawan))->assertRedirect();
        $this->assertFalse($this->karyawan->fresh()->is_active);

        $this->patch(route('users.toggle', $this->karyawan))->assertRedirect();
        $this->assertTrue($this->karyawan->fresh()->is_active);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        User::factory()->karyawan()->inactive()->create(['email' => 'off@example.com']);

        $this->post('/login', ['email' => 'off@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_user_can_still_log_in(): void
    {
        $this->post('/login', ['email' => $this->karyawan->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($this->karyawan);
    }

    public function test_existing_session_of_inactive_user_is_ended(): void
    {
        $inactive = User::factory()->karyawan()->inactive()->create(['store_id' => $this->store->id]);

        $this->actingAs($inactive)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
