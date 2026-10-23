@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Dashboard</li>
@endsection

@section('content')
    <div id="dashboard-root" data-dashboard-data-url="{{ route('admin.dashboard.data') }}">

        <div class="d-flex justify-content-end mb-3">
            <span class="text-muted fs-7" id="dash-updated-at">Diperbarui pukul {{ $updatedAt }}</span>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <h3 class="card-title">Kuota tiket</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold fs-4"><span data-dash="ticketsTaken">{{ $ticketsTaken }}</span></span>
                            <span class="text-muted">dari <span data-dash="quota">{{ $quota }}</span> tiket
                                (<span data-dash="ticketsRemaining">{{ $ticketsRemaining }}</span> sisa)</span>
                        </div>
                        <div class="progress h-10px">
                            <div id="quotaBar"
                                 class="progress-bar {{ $quotaPercent > 90 ? 'bg-danger' : 'bg-primary' }}"
                                 role="progressbar" style="width: {{ $quotaPercent }}%"
                                 aria-valuenow="{{ $quotaPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <h3 class="card-title">Check-in gelang</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold fs-4">
                                <span data-dash="checkedInTickets">{{ $checkedInTickets }}</span> / <span data-dash="totalTickets">{{ $totalTickets }}</span> tiket
                            </span>
                            <span class="text-muted"><span data-dash="recentScans">{{ $recentScans }}</span> scan sukses 10 menit terakhir</span>
                        </div>
                        <div class="progress h-10px">
                            <div id="checkinBar" class="progress-bar bg-success" role="progressbar"
                                 style="width: {{ $checkinPercent }}%"
                                 aria-valuenow="{{ $checkinPercent }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-12">
                <div class="card card-flush">
                    <div class="card-body">
                        <div class="fs-2hx fw-bold" data-dash="registrantCount">{{ $registrantCount }}</div>
                        <div class="fs-7 text-muted">Jumlah pendaftar</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-12">
                <div class="card card-flush h-100">
                    <div class="card-header">
                        <h3 class="card-title">Pendaftar terbaru</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div id="dash-recent-list">
                            @forelse ($recent as $r)
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div>
                                        <div class="fw-bold">{{ $r['name'] }}</div>
                                        <div class="text-muted fs-7">{{ $r['code'] }} &middot; {{ $r['regency'] ?? '-' }} &middot; {{ $r['ticket_qty'] }} tiket</div>
                                    </div>
                                    <a href="{{ $r['url'] }}" class="btn btn-sm btn-light-primary">Detail</a>
                                </div>
                            @empty
                                <div class="text-muted p-4">Belum ada pendaftar.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="@versionedAsset('js/admin-dashboard.js')"></script>
@endpush
