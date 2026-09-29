@extends('layouts.public')

@section('title', 'Pendaftaran berhasil')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="page-shell centered-page">
        <x-header variant="logo" :logo-size="96">
            <a href="{{ url('/cari-tiket') }}" class="nav-link">Cari tiket saya</a>
        </x-header>

        <div class="centered-card-wrap">
            <div class="card card--success">
                <div class="icon-circle icon-circle--success">
                    <x-icon name="check" :size="40" :stroke="3" />
                </div>

                <h1 class="h1-page">Pendaftaran berhasil</h1>
                <p class="card__body">E-ticket sudah dikirim ke email dan WhatsApp Anda. Jika belum masuk dalam beberapa menit, cek folder spam.</p>

                <div class="summary-box">
                    <div class="summary-box__col">
                        <span class="summary-box__label">Kode registrasi</span>
                        <span class="summary-box__value">{{ $registration->code }}</span>
                    </div>
                    <div class="summary-box__col summary-box__col--right">
                        <span class="summary-box__label">Jumlah</span>
                        <span class="summary-box__value">{{ $registration->ticket_qty }} tiket</span>
                    </div>
                </div>

                <div class="success-actions">
                    <x-button-primary :href="url('/tiket/'.$registration->token)">Lihat e-ticket</x-button-primary>
                    <a href="{{ route('home') }}" class="success-actions__secondary">Kembali ke beranda</a>
                </div>

                <div class="card-note u-desktop-only-block">Simpan kode registrasi ini. Petugas bisa mencari data Anda dengan kode ini jika QR tidak terbaca.</div>
            </div>
        </div>

        <div class="page-note u-mobile-only-block">Simpan kode registrasi ini. Petugas bisa mencari data Anda dengan kode ini jika QR tidak terbaca.</div>
    </div>
@endsection
