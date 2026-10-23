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

@php
    $hasFilter = $keyword !== '' || $role !== '';
@endphp

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <form method="GET" data-live-filter class="card-header border-0 pt-6 gap-3">
            <div class="card-title w-100 w-md-auto m-0">
                <div class="d-flex align-items-center position-relative w-100">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span class="path2"></span></i>
                    <input type="search" name="q" class="form-control form-control-solid w-100 w-md-300px ps-13"
                           placeholder="Cari nama atau username" aria-label="Cari akun" value="{{ $keyword }}">
                </div>
            </div>

            <div class="card-toolbar w-100 w-md-auto m-0">
                <div class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <select name="role" class="form-select form-select-solid flex-grow-1 w-md-175px" aria-label="Tipe akun">
                        <option value="">Semua tipe akun</option>
                        @foreach (\App\Enums\UserRole::cases() as $case)
                            <option value="{{ $case->value }}" @selected($role === $case->value)>{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    <span data-live-reset>
                        @if ($hasFilter)
                            <a href="{{ route('admin.users.index') }}" class="btn btn-light">Reset</a>
                        @endif
                    </span>
                </div>
            </div>
        </form>
        <div class="card-body pt-0" data-live-results aria-live="polite">
            @if ($users->isEmpty())
                @if ($hasFilter)
                    <x-admin-empty-state
                        icon="ki-profile-user"
                        title="Tidak ada akun yang cocok."
                        description="Coba ubah kata kunci atau filter pencarian."
                        :action-url="route('admin.users.index')"
                        action-label="Hapus filter"
                    />
                @else
                    <x-admin-empty-state
                        icon="ki-profile-user"
                        title="Belum ada akun petugas."
                        description="Tambahkan akun admin atau petugas scan untuk mulai mengelola hari-H."
                        :action-url="route('admin.users.create')"
                        action-label="Tambah Akun"
                    />
                @endif
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

@push('scripts')
    <script src="@versionedAsset('js/admin-live-filter.js')"></script>
@endpush
