@extends('layouts.admin')

@section('title', 'Ubah Akun Petugas')
@section('page_title', 'Ubah Akun Petugas')

@section('content')
    @php $isSelf = auth()->id() === $user->id; @endphp

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="mb-5">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}">
                    @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}">
                    @error('username') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Kata sandi baru (kosongkan jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                    @error('password') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select" {{ $isSelf ? 'disabled' : '' }}>
                        <option value="scanner" {{ old('role', $user->role->value) === 'scanner' ? 'selected' : '' }}>Petugas Scan</option>
                        <option value="admin" {{ old('role', $user->role->value) === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @if ($isSelf)
                        <input type="hidden" name="role" value="{{ $user->role->value }}">
                        <div class="fs-7 text-muted mt-1">Anda tidak bisa mengubah role akun Anda sendiri.</div>
                    @endif
                    @error('role') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-5">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                           {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                           {{ $isSelf ? 'disabled' : '' }}>
                    <label class="form-check-label" for="is_active">Akun aktif</label>
                    @if ($isSelf)
                        <input type="hidden" name="is_active" value="1">
                        <div class="fs-7 text-muted mt-1">Anda tidak bisa menonaktifkan akun Anda sendiri.</div>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
@endsection
