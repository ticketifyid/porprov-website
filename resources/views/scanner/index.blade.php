@extends('layouts.scanner')

@section('title', 'Scanner')

@section('content')
    <div class="scanner-modes mb-5">
        <button type="button" class="btn btn-lg btn-light btn-active-primary" data-scan-mode="camera" aria-pressed="false">Kamera</button>
        <button type="button" class="btn btn-lg btn-light btn-active-primary" data-scan-mode="hardware" aria-pressed="false">Alat scanner</button>
    </div>

    @unless (request()->secure() || app()->environment('local'))
        <div class="alert alert-warning mb-5">
            Halaman ini dibuka tanpa HTTPS, jadi browser tidak akan mengizinkan kamera.
            Buka lewat alamat https:// atau pakai mode Alat scanner.
        </div>
    @endunless

    {{-- Panel hasil: diisi scanner.js, satu-satunya tempat hasil ditampilkan. --}}
    <div id="scan-result" class="scan-result mb-5" role="status" aria-live="assertive" hidden></div>

    <div id="scanner-camera" class="card mb-5" hidden>
        <div class="card-body p-4">
            <div id="reader"></div>
            <p class="scanner-hint text-gray-700 fs-7 text-center mb-0 mt-4" id="camera-status">Menyiapkan kamera…</p>
        </div>
    </div>

    <div id="scanner-hardware" class="scanner-hardware bg-light-primary rounded border-primary border border-dashed p-6 mb-5" hidden>
        <div class="fs-6 fw-semibold text-gray-800">
            Siap menerima alat scanner. Arahkan alat ke QR peserta. Gelang langsung tertukar tanpa konfirmasi.
        </div>
    </div>

    <div id="scanner-manual" class="card">
        <div class="card-body p-4">
            <form id="manual-form" autocomplete="off">
                <label class="form-label fw-bold fs-6 text-gray-900" for="manual-input">Pencarian manual (kode, nama, atau nomor HP)</label>
                <div class="d-flex gap-3">
                    <input type="text" id="manual-input" name="query" class="form-control form-control-lg form-control-solid"
                           inputmode="search" enterkeyhint="search" maxlength="100" placeholder="PJT26-7K3M9Q">
                    <button type="submit" class="btn btn-lg btn-primary flex-shrink-0">Cari</button>
                </div>
            </form>

            <p class="text-gray-700 fs-7 mt-3 mb-0" id="manual-note"></p>
            <ul class="scan-candidates" id="manual-candidates"></ul>
        </div>
    </div>
@endsection
