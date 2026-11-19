<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreOwnerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $override = []): array
    {
        return array_merge([
            'store_name' => 'Toko Berkah',
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $override);
    }

    public function test_registration_creates_store_and_admin_user(): void
    {
        $response = $this->post('/register', $this->payload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertSame('Toko Berkah', $user->store->name);
        $this->assertDatabaseCount('stores', 1);
    }

    public function test_store_name_is_required(): void
    {
        $this->post('/register', $this->payload(['store_name' => '']))
            ->assertSessionHasErrors('store_name');

        $this->assertGuest();
        $this->assertDatabaseCount('stores', 0);
    }

    public function test_role_cannot_be_injected_through_registration(): void
    {
        $this->post('/register', $this->payload(['role' => 'karyawan', 'store_id' => 999]));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertNotSame(999, $user->store_id);
    }

    public function test_each_registration_gets_its_own_store(): void
    {
        $this->post('/register', $this->payload());
        auth()->logout();
        $this->post('/register', $this->payload(['email' => 'siti@example.com', 'store_name' => 'Toko Siti']));

        $this->assertDatabaseCount('stores', 2);
        $this->assertNotSame(
            User::where('email', 'budi@example.com')->value('store_id'),
            User::where('email', 'siti@example.com')->value('store_id'),
        );
    }
}
