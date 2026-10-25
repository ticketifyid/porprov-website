@extends('layouts.admin')

@section('title', 'Log Penukaran')
@section('page_title', 'Log Penukaran')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Log Penukaran</li>
@endsection

@php
    $hasFilter = $keyword !== '' || $result !== '' || $method !== '';
    $resultBadges = [
        'success' => ['badge-light-success', 'Berhasil'],
        'already_redeemed' => ['badge-light-warning', 'Sudah ditukar'],
        'not_found' => ['badge-light-danger', 'Tidak ditemukan'],
        'cancelled' => ['badge-light-danger', 'Dibatalkan'],
    ];
    $methodLabels = ['camera' => 'Kamera', 'hardware' => 'Alat scanner', 'manual' => 'Manual'];
@endphp

@section('content')
    <div class="card">
        {{-- Pola daftar Metronic yang sama dengan daftar peserta. --}}
        <form method="GET" data-live-filter class="card-header border-0 pt-6 gap-3">
            <div class="card-title w-100 w-md-auto m-0">
                <div class="d-flex align-items-center position-relative w-100">
                    <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5"><span class="path1"></span><span class="path2"></span></i>
                    <input type="search" name="q" class="form-control form-control-solid w-100 w-md-300px ps-13"
                           placeholder="Cari peserta, kode, atau petugas" aria-label="Cari log penukaran" value="{{ $keyword }}">
                </div>
            </div>

            <div class="card-toolbar w-100 w-md-auto m-0">
                <div class="d-flex flex-wrap align-items-center gap-3 w-100">
                    <select name="result" class="form-select form-select-solid flex-grow-1 w-md-175px" aria-label="Hasil">
                        <option value="">Semua hasil</option>
                        @foreach ($resultBadges as $value => [, $label])
                            <option value="{{ $value }}" @selected($result === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="method" class="form-select form-select-solid flex-grow-1 w-md-175px" aria-label="Metode">
                        <option value="">Semua metode</option>
                        @foreach ($methodLabels as $value => $label)
                            <option value="{{ $value }}" @selected($method === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span data-live-reset>
                        @if ($hasFilter)
                            <a href="{{ route('admin.scan-logs.index') }}" class="btn btn-light">Reset</a>
                        @endif
                    </span>
                </div>
            </div>
        </form>
        <div class="card-body pt-0" data-live-results aria-live="polite">
            @if ($logs->isEmpty())
                <x-admin-empty-state
                    icon="ki-search-list"
                    :title="$hasFilter ? 'Tidak ada log yang cocok.' : 'Belum ada log penukaran.'"
                    :description="$hasFilter ? 'Coba ubah kata kunci atau filter pencarian.' : 'Setiap percobaan scan oleh petugas akan tercatat di sini.'"
                    :action-url="$hasFilter ? route('admin.scan-logs.index') : null"
                    :action-label="$hasFilter ? 'Hapus filter' : null"
                />
            @else
                {{-- Tabel di layar >= 768px --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-row-bordered align-middle">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Peserta</th>
                                <th>Petugas</th>
                                <th>Metode</th>
                                <th>Hasil</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($logs as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->scanned_at->locale('id')->translatedFormat('j M Y, H.i.s') }}</td>
                                    <td>
                                        @if ($log->registration)
                                            <div class="fw-bold">{{ $log->registration->name }}</div>
                                            <div class="text-muted fs-7">{{ $log->registration->code }} &middot; {{ $log->registration->ticket_qty }} tiket</div>
                                        @else
                                            <span class="text-muted">QR tidak dikenal</span>
                                        @endif
                                    </td>
                                    <td>{{ $log->user?->name }}</td>
                                    <td>{{ $methodLabels[$log->method] ?? $log->method }}</td>
                                    <td>
                                        <span class="badge {{ $resultBadges[$log->result][0] ?? 'badge-light' }}">{{ $resultBadges[$log->result][1] ?? $log->result }}</span>
                                    </td>
                                    <td>
                                        @if ($log->registration)
                                            <a href="{{ route('admin.registrations.show', $log->registration) }}" class="btn btn-sm btn-light-primary">Detail</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Kartu per baris di layar < 768px --}}
                <div class="d-md-none">
                    @foreach ($logs as $log)
                        <div class="card card-bordered mb-3">
                            <div class="card-body d-flex justify-content-between align-items-start gap-3 p-4">
                                <div class="flex-grow-1">
                                    @if ($log->registration)
                                        <div class="fw-bold fs-6">{{ $log->registration->name }}</div>
                                        <div class="text-muted fs-7">{{ $log->registration->code }} &middot; {{ $log->registration->ticket_qty }} tiket</div>
                                    @else
                                        <div class="fw-bold fs-6 text-muted">QR tidak dikenal</div>
                                    @endif
                                    <div class="text-muted fs-7">{{ $log->scanned_at->locale('id')->translatedFormat('j M Y, H.i.s') }}</div>
                                    <div class="text-muted fs-7">{{ $log->user?->name }} &middot; {{ $methodLabels[$log->method] ?? $log->method }}</div>
                                    <div class="mt-2">
                                        <span class="badge {{ $resultBadges[$log->result][0] ?? 'badge-light' }}">{{ $resultBadges[$log->result][1] ?? $log->result }}</span>
                                    </div>
                                </div>
                                @if ($log->registration)
                                    <a href="{{ route('admin.registrations.show', $log->registration) }}" class="btn btn-sm btn-light-primary flex-shrink-0">Detail</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                {{ $logs->links() }}
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="@versionedAsset('js/admin-live-filter.js')"></script>
@endpush
