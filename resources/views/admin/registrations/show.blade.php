@extends('layouts.admin')

@section('title', 'Detail Peserta')
@section('page_title', 'Detail Peserta')

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-5">
        <div class="card-body">
            <h3>{{ $registration->name }}</h3>
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
                            <tr>
                                <td>{{ $log->channel }}</td>
                                <td>{{ $log->status }}</td>
                                <td>{{ $log->attempts }}</td>
                                <td>{{ $log->sent_at?->locale('id')->translatedFormat('j F Y, H.i') ?? '-' }}</td>
                                <td>{{ $log->last_error }}</td>
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
            <div class="card-body">
                <form method="POST" action="{{ route('admin.registrations.cancel', $registration) }}"
                      onsubmit="return confirm('Batalkan registrasi ini? Kuota akan dikembalikan.');">
                    @csrf
                    <button type="submit" class="btn btn-danger">Batalkan registrasi</button>
                </form>
            </div>
        </div>
    @endif
@endsection
