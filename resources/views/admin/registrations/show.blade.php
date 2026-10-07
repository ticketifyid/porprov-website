@extends('layouts.admin')

@section('title', 'Detail Peserta')
@section('page_title', 'Detail Peserta')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Admin</a></li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted"><a href="{{ route('admin.registrations.index') }}" class="text-muted text-hover-primary">Peserta</a></li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Detail</li>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">{{ $registration->name }}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted mb-1">Kode: {{ $registration->code }}</p>
            <p class="text-muted mb-1">Kab/Kota: {{ $registration->regency?->name }}</p>
            <p class="text-muted mb-1">Email: {{ $registration->email }}</p>
            <p class="text-muted mb-1">No. HP: {{ $registration->phone }}</p>
            <p class="text-muted mb-1">Jumlah tiket: {{ $registration->ticket_qty }}</p>
            <p class="text-muted mb-3">Verifikasi: {{ \App\Models\Registration::VERIFIED_VIA[$registration->verified_via] ?? '—' }}</p>

            <p class="mb-0">
                @if ($registration->cancelled_at)
                    <span class="badge badge-light-danger">
                        Dibatalkan {{ $registration->cancelled_at->locale('id')->translatedFormat('j F Y, H.i') }}
                        oleh {{ $registration->canceller?->name }}
                    </span>
                @elseif ($registration->redeemed_at)
                    <span class="badge badge-light-success">
                        Sudah ditukar {{ $registration->redeemed_at->locale('id')->translatedFormat('j F Y, H.i') }}
                        oleh {{ $registration->redeemer?->name }}
                    </span>
                @else
                    <span class="badge badge-light-warning">Belum ditukar</span>
                @endif
            </p>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header">
            <h3 class="card-title">Status notifikasi</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive mb-5">
                <table class="table table-row-bordered">
                    <thead>
                        <tr>
                            <th>Kanal</th>
                            <th>Status</th>
                            <th>Percobaan</th>
                            <th>Terkirim pada</th>
                            <th>Error terakhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registration->notificationLogs as $log)
                            @php
                                $statusBadge = match ($log->status) {
                                    'sent' => ['badge-light-success', 'Terkirim'],
                                    'pending' => ['badge-light-warning', 'Menunggu'],
                                    'failed' => ['badge-light-danger', 'Gagal'],
                                    default => ['badge-light', $log->status],
                                };
                            @endphp
                            <tr>
                                <td>{{ $log->channel }}</td>
                                <td><span class="badge {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span></td>
                                <td>{{ $log->attempts }}</td>
                                <td>{{ $log->sent_at?->locale('id')->translatedFormat('j F Y, H.i') ?? '-' }}</td>
                                <td>
                                    @if ($log->last_error)
                                        <span class="text-muted">Error teknis:</span>
                                        <span class="text-truncate notif-error-text d-inline-block align-bottom"
                                              data-bs-toggle="tooltip" title="{{ $log->last_error }}">{{ $log->last_error }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Belum ada percobaan pengiriman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @unless ($registration->cancelled_at)
                <form method="POST" action="{{ route('admin.registrations.resend', $registration) }}">
                    @csrf
                    <button type="submit" class="btn btn-light-primary">Kirim ulang notifikasi</button>
                </form>
            @endunless
        </div>
    </div>

    @if (! $registration->cancelled_at && ! $registration->redeemed_at)
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Zona berbahaya</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.registrations.cancel', $registration) }}"
                      data-confirm="Batalkan registrasi ini?"
                      data-confirm-detail="Kuota {{ $registration->ticket_qty }} tiket akan dikembalikan. Tindakan ini tidak bisa dibatalkan.">
                    @csrf
                    <button type="submit" class="btn btn-danger">Batalkan registrasi</button>
                </form>
            </div>
        </div>
    @endif
@endsection
