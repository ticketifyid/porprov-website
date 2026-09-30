@extends('layouts.admin')

@section('title', 'Peserta')
@section('page_title', 'Peserta')

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex w-100 pt-3">
                <input type="text" name="q" class="form-control me-2" placeholder="Cari nama, kode, atau nomor HP"
                       value="{{ $keyword }}">
                <select name="via" class="form-select me-2 w-auto" aria-label="Jalur verifikasi">
                    <option value="">Semua verifikasi</option>
                    @foreach (\App\Models\Registration::VERIFIED_VIA as $value => $label)
                        <option value="{{ $value }}" @selected($via === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-primary">Cari</button>
            </form>
        </div>
        <div class="card-body">
            <div class="table-responsive">
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
                        @forelse ($registrations as $registration)
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
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.registrations.show', $registration) }}" class="btn btn-sm btn-light-primary">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $registrations->links() }}
        </div>
    </div>
@endsection
