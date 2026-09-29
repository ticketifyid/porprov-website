@extends('layouts.admin')

@section('title', 'Peserta')
@section('page_title', 'Peserta')

@section('content')
    <div class="card">
        <div class="card-header">
            <form method="GET" class="d-flex w-100 pt-3">
                <input type="text" name="q" class="form-control me-2" placeholder="Cari nama, kode, atau nomor HP"
                       value="{{ $keyword }}">
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
                                    <a href="{{ route('admin.registrations.show', $registration) }}" class="btn btn-sm btn-light-primary">Detail</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Tidak ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $registrations->links() }}
        </div>
    </div>
@endsection
