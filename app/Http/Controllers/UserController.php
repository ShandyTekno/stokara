<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $users = User::query()
            ->where('store_id', $actor->store_id)
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")
            ))
            ->orderByRaw("role = 'admin' desc")
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'role_label' => $u->role->label(),
                'is_active' => $u->is_active,
                'is_self' => $u->id === $actor->id,
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $request->input('search', '')],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Role dan toko diset di server; input role/store_id dari form diabaikan.
        $user = User::create([
            'store_id' => $request->user()->store_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => Role::Karyawan,
        ]);
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('users.index')->with('success', "Akun karyawan {$user->name} berhasil dibuat.");
    }

    public function edit(User $user): Response
    {
        $this->ensureManageable($user);

        return Inertia::render('Users/Edit', [
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->fill(['name' => $data['name'], 'email' => $data['email']]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
            $this->revokeSessions($user);
        }

        $user->save();

        return redirect()->route('users.index')->with('success', 'Akun karyawan berhasil diperbarui.');
    }

    public function toggle(User $user): RedirectResponse
    {
        $this->ensureManageable($user);

        $user->is_active = ! $user->is_active;
        $user->save();

        if (! $user->is_active) {
            $this->revokeSessions($user);
        }

        return back()->with('success', $user->is_active
            ? "Akun {$user->name} diaktifkan kembali."
            : "Akun {$user->name} dinonaktifkan.");
    }

    private function ensureManageable(User $user): void
    {
        abort_if($user->isAdmin(), 403, 'Akun admin tidak dapat dikelola dari halaman ini.');
    }

    /** Putus semua sesi aktif pengguna (login di perangkat lain ikut keluar). */
    private function revokeSessions(User $user): void
    {
        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
