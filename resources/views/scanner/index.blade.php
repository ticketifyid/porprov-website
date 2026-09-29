@extends('layouts.scanner')

@section('title', 'Scanner')

@section('content')
    <div class="scanner-modes">
        <button type="button" class="scanner-mode" data-scan-mode="camera" aria-pressed="false">Kamera</button>
        <button type="button" class="scanner-mode" data-scan-mode="hardware" aria-pressed="false">Alat scanner</button>
    </div>

    @unless (request()->secure() || app()->environment('local'))
        <p class="scanner-warn">
            Halaman ini dibuka tanpa HTTPS, jadi browser tidak akan mengizinkan kamera.
            Buka lewat alamat https:// atau pakai mode Alat scanner.
        </p>
    @endunless

    {{-- Panel hasil: diisi scanner.js, satu-satunya tempat hasil ditampilkan. --}}
    <div id="scan-result" class="scan-result" role="status" aria-live="assertive" hidden></div>

    <div id="scanner-camera" class="scanner-camera" hidden>
        <div id="reader"></div>
        <p class="scanner-hint" id="camera-status">Menyiapkan kamera…</p>
    </div>

    <div id="scanner-hardware" class="scanner-hardware" hidden>
        Siap menerima alat scanner. Arahkan alat ke QR peserta — gelang langsung tertukar tanpa konfirmasi.
    </div>

    <div id="scanner-manual" class="scanner-manual">
        <form id="manual-form" autocomplete="off">
            <label class="scanner-manual__label" for="manual-input">Pencarian manual (kode, nama, atau nomor HP)</label>
            <div class="scanner-manual__row">
                <input type="text" id="manual-input" name="query" class="scanner-manual__input"
                       inputmode="search" enterkeyhint="search" maxlength="100" placeholder="PJT26-7K3M9Q">
                <button type="submit" class="scanner-manual__submit">Cari</button>
            </div>
        </form>

        <p class="scanner-manual__note" id="manual-note"></p>
        <ul class="scan-candidates" id="manual-candidates"></ul>
    </div>
@endsection
