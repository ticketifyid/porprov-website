@extends('layouts.admin')

@section('title', 'Peserta')
@section('page_title', 'Peserta')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Peserta</li>
@endsection

@php
    $hasFilter = $keyword !== '' || $status !== '' || $notif !== '';
@endphp

@section('content')
    @if ($notif === 'failed')
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-5">
            <div>Menampilkan hanya peserta dengan notifikasi gagal.</div>
            <a href="{{ route('admin.registrations.index') }}" class="btn btn-sm btn-light">Hapus filter</a>
        </div>
    @endif

    <div class="card">
        {{-- Pola daftar Metronic: pencarian di kiri header kartu, filter dan tombol di kanan. --}}
        <form method="GET" data-live-filter class="card-header border-0 pt-6 gap-3">
            @if ($notif !== '')
                <input type="hidden" name="notif" value="{{ $notif }}">
            @endif

            <div class="card-title w-100 w-md-auto m-0">
                <div class="d-flex align-items-center position-relative w-100">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span class="path2"></span></i>
                    <input type="search" name="q" class="form-control form-control-solid w-100 w-md-300px ps-13"
                           placeholder="Cari nama, kode, HP, atau email" aria-label="Cari peserta" value="{{ $keyword }}">
                </div>
            </div>

            <div class="card-toolbar w-100 w-md-auto m-0">
                <div class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <select name="status" class="form-select form-select-solid flex-grow-1 w-md-175px" aria-label="Status">
                        <option value="">Semua status</option>
                        @foreach (['pending' => 'Belum ditukar', 'redeemed' => 'Sudah ditukar', 'cancelled' => 'Dibatalkan'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span data-live-reset>
                        @if ($hasFilter)
                            <a href="{{ route('admin.registrations.index') }}" class="btn btn-light">Reset</a>
                        @endif
                    </span>
                </div>
            </div>
        </form>
        <div class="card-body pt-0" data-live-results aria-live="polite">
            @if ($registrations->isEmpty())
                <x-admin-empty-state
                    icon="ki-search-list"
                    :title="$hasFilter ? 'Tidak ada peserta yang cocok.' : 'Belum ada peserta yang mendaftar.'"
                    :description="$hasFilter ? 'Coba ubah kata kunci atau filter pencarian.' : 'Peserta yang mendaftar akan muncul di sini.'"
                    :action-url="$hasFilter ? route('admin.registrations.index') : null"
                    :action-label="$hasFilter ? 'Hapus filter' : null"
                />
            @else
                {{-- Tabel di layar >= 768px --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama</th>
                                <th>No. HP</th>
                                <th>Email</th>
                                <th>Kab/Kota</th>
                                <th>Tiket</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registrations as $registration)
                                <tr>
                                    <td>{{ $registration->code }}</td>
                                    <td>{{ $registration->name }}</td>
                                    <td>{{ $registration->phone }}</td>
                                    <td>{{ $registration->email }}</td>
                                    <td>{{ $registration->regency?->name }}</td>
                                    <td>{{ $registration->ticket_qty }}</td>
                                    <td>
                                        @if ($registration->cancelled_at)
                                            <span class="badge badge-light-danger">Dibatalkan</span>
                                        @elseif ($registration->redeemed_at)
                                            <span class="badge badge-light-success">Sudah ditukar</span>
                                        @else
                                            <span class="badge badge-light-warning">Belum ditukar</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.registrations.show', $registration) }}" class="btn btn-sm btn-light-primary">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kartu per baris di layar < 768px, supaya status dan tombol aksi selalu terlihat --}}
                <div class="d-md-none">
                    @foreach ($registrations as $registration)
                        <div class="card card-bordered mb-3">
                            <div class="card-body d-flex justify-content-between align-items-start gap-3 p-4">
                                <div class="flex-grow-1">
                                    <div class="fw-bold fs-6">{{ $registration->name }}</div>
                                    <div class="text-muted fs-7">{{ $registration->code }} &middot; {{ $registration->regency?->name }}</div>
                                    <div class="text-muted fs-7">{{ $registration->phone }}</div>
                                    <div class="text-muted fs-7 text-break">{{ $registration->email }}</div>
                                    <div class="text-muted fs-7">{{ $registration->ticket_qty }} tiket</div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        @if ($registration->cancelled_at)
                                            <span class="badge badge-light-danger">Dibatalkan</span>
                                        @elseif ($registration->redeemed_at)
                                            <span class="badge badge-light-success">Sudah ditukar</span>
                                        @else
                                            <span class="badge badge-light-warning">Belum ditukar</span>
                                        @endif
                                    </div>
                                </div>
                                <a href="{{ route('admin.registrations.show', $registration) }}" class="btn btn-sm btn-light-primary flex-shrink-0">Detail</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{ $registrations->links() }}
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="@versionedAsset('js/admin-live-filter.js')"></script>
@endpush
