@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
    <div class="row g-5 g-xl-8 mb-5">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="fs-2hx fw-bold">{{ $ticketsTaken }}</div>
                    <div class="fs-7 text-muted">Tiket terpakai</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="fs-2hx fw-bold">{{ $ticketsRemaining }}</div>
                    <div class="fs-7 text-muted">Sisa kuota</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="fs-2hx fw-bold">{{ $registrantCount }}</div>
                    <div class="fs-7 text-muted">Jumlah pendaftar</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-flush h-100">
                <div class="card-body">
                    <div class="fs-2hx fw-bold">{{ $checkedInTickets }}</div>
                    <div class="fs-7 text-muted">Gelang sudah check-in</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h3 class="mb-0">Selamat datang, {{ auth()->user()->name }}.</h3>
        </div>
    </div>
@endsection
