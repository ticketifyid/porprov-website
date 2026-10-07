@extends('layouts.admin')

@section('title', 'Peserta')
@section('page_title', 'Peserta')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Peserta</li>
@endsection

@php
    $hasFilter = $keyword !== '' || $via !== '' || $notif !== '';
@endphp

@section('content')
    @if ($notif === 'failed')
        <div class="alert alert-warning d-flex align-items-center justify-content-between mb-5">
            <div>Menampilkan hanya peserta dengan notifikasi gagal.</div>
            <a href="{{ route('admin.registrations.index') }}" class="btn btn-sm btn-light">Hapus filter</a>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Daftar peserta</h3>
            <form method="GET" class="card-toolbar w-100 w-md-auto d-flex flex-wrap gap-2 mt-3 mt-md-0">
                @if ($notif !== '')
                    <input type="hidden" name="notif" value="{{ $notif }}">
                @endif
                <input type="text" name="q" class="form-control form-control-sm flex-grow-1" style="min-width: 160px;"
                       placeholder="Cari nama, kode, atau nomor HP" value="{{ $keyword }}">
                <select name="via" class="form-select form-select-sm w-auto" aria-label="Jalur verifikasi">
                    <option value="">Semua verifikasi</option>
                    @foreach (\App\Models\Registration::VERIFIED_VIA as $value => $label)
                        <option value="{{ $value }}" @selected($via === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-primary">Cari</button>
            </form>
        </div>
        <div class="card-body">
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
                                <th>Kab/Kota</th>
                                <th>Tiket</th>
                                <th>Status</th>
                                <th>Verifikasi</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registrations as $registration)
                                <tr>
                                    <td>{{ $registration->code }}</td>
                                    <td>{{ $registration->name }}</td>
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
                                        @if ($registration->verified_via === 'captcha')
                                            <span class="badge badge-light-info">Captcha gambar</span>
                                        @elseif ($registration->verified_via === 'turnstile')
                                            <span class="badge badge-light">Turnstile</span>
                                        @else
                                            <span class="text-muted">&mdash;</span>
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
                                    <div class="text-muted fs-7">{{ $registration->ticket_qty }} tiket</div>
                                    <div class="mt-2 d-flex flex-wrap gap-1">
                                        @if ($registration->cancelled_at)
                                            <span class="badge badge-light-danger">Dibatalkan</span>
                                        @elseif ($registration->redeemed_at)
                                            <span class="badge badge-light-success">Sudah ditukar</span>
                                        @else
                                            <span class="badge badge-light-warning">Belum ditukar</span>
                                        @endif
                                        @if ($registration->verified_via === 'captcha')
                                            <span class="badge badge-light-info">Captcha gambar</span>
                                        @elseif ($registration->verified_via === 'turnstile')
                                            <span class="badge badge-light">Turnstile</span>
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
