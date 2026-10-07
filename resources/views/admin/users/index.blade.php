@extends('layouts.admin')

@section('title', 'Akun Petugas')
@section('page_title', 'Akun Petugas')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Akun Petugas</li>
@endsection

@section('toolbar_actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Tambah Akun</a>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar akun petugas</h3>
        </div>
        <div class="card-body">
            @if ($users->isEmpty())
                <x-admin-empty-state
                    icon="ki-profile-user"
                    title="Belum ada akun petugas."
                    description="Tambahkan akun admin atau petugas scan untuk mulai mengelola hari-H."
                    :action-url="route('admin.users.create')"
                    action-label="Tambah Akun"
                />
            @else
                {{-- Tabel di layar >= 768px --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->username }}</td>
                                    <td>{{ $user->role->label() }}</td>
                                    <td>
                                        @if ($user->is_active)
                                            <span class="badge badge-light-success">Aktif</span>
                                        @else
                                            <span class="badge badge-light-danger">Nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light-primary">Ubah</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kartu per baris di layar < 768px, supaya status dan tombol Ubah selalu terlihat --}}
                <div class="d-md-none">
                    @foreach ($users as $user)
                        <div class="card card-bordered mb-3">
                            <div class="card-body d-flex justify-content-between align-items-start gap-3 p-4">
                                <div>
                                    <div class="fw-bold fs-6">{{ $user->name }}</div>
                                    <div class="text-muted fs-7">{{ $user->username }} &middot; {{ $user->role->label() }}</div>
                                    <div class="mt-2">
                                        @if ($user->is_active)
                                            <span class="badge badge-light-success">Aktif</span>
                                        @else
                                            <span class="badge badge-light-danger">Nonaktif</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-light-primary flex-shrink-0">Ubah</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endsection
