<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create([
            'name' => $request->validated('name'),
            'username' => $request->validated('username'),
            'password' => Hash::make($request->validated('password')),
            'role' => $request->validated('role'),
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Akun berhasil dibuat.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    /**
     * Validasi inline, mengikuti preseden LoginController/ScanController
     * (docs/struktur.md hanya menyediakan StoreUserRequest untuk Admin/).
     *
     * Dua aturan tambahan pemilik proyek untuk Fase 8: admin tidak bisa
     * menonaktifkan/menurunkan role akunnya sendiri, dan tidak boleh ada
     * kondisi tanpa satu admin aktif pun.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:12'],
            'role' => ['required', Rule::in(['admin', 'scanner'])],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah dipakai.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'role.required' => 'Role wajib dipilih.',
        ]);

        $isSelf = $request->user()->id === $user->id;

        if ($isSelf && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withInput()->withErrors([
                'role' => 'Anda tidak bisa menonaktifkan atau menurunkan role akun Anda sendiri.',
            ]);
        }

        $willRemainActiveAdmin = $data['role'] === 'admin' && $data['is_active'];

        if (! $willRemainActiveAdmin) {
            $otherActiveAdminExists = User::query()
                ->whereKeyNot($user->id)
                ->where('role', 'admin')
                ->where('is_active', true)
                ->exists();

            if (! $otherActiveAdminExists) {
                return back()->withInput()->withErrors([
                    'role' => 'Tidak boleh ada kondisi tanpa satu admin aktif pun. Aktifkan atau tunjuk admin lain terlebih dahulu.',
                ]);
            }
        }

        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'Akun berhasil diperbarui.');
    }
}
