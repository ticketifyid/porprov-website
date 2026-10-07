@extends('layouts.admin')

@section('title', 'Tambah Akun Petugas')
@section('page_title', 'Tambah Akun Petugas')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Admin</a></li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.users.index') }}" class="text-muted text-hover-primary">Akun Petugas</a></li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Tambah</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Formulir akun petugas</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf

                <div class="mb-5">
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}">
                    @error('name') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" value="{{ old('username') }}">
                    @error('username') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Kata sandi</label>
                    <input type="password" name="password" class="form-control">
                    @error('password') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select">
                        <option value="scanner" {{ old('role', 'scanner') === 'scanner' ? 'selected' : '' }}>Petugas Scan</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @error('role') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
@endsection
