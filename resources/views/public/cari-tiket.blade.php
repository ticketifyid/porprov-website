@extends('layouts.public')

@section('title', 'Cari tiket saya')

@php
    $help = 'Tidak menerima apa pun setelah beberapa menit? Datang ke meja bantuan di lokasi dengan menunjukkan KTP; petugas bisa mencari data Anda.';
@endphp

@section('content')
    <div class="page-shell find-page">
        <x-header variant="back">
            <a href="{{ route('daftar') }}" class="btn-primary-sm">Daftar</a>
        </x-header>

        <div class="find-wrap">
            <form method="POST" action="{{ route('cari-tiket.submit') }}" class="find-card" novalidate>
                @csrf

                <div class="find-card__intro">
                    <h1 class="h1-page">Cari tiket saya</h1>
                    <p class="find-card__lead">Masukkan nomor WhatsApp atau email yang dipakai saat mendaftar. Link e-ticket akan dikirim ulang ke {{ $whatsappActive ? 'kontak tersebut' : 'email yang terdaftar' }}.</p>
                </div>

                <x-field
                    label="Nomor WhatsApp atau email"
                    name="contact"
                    id="contact"
                    autocomplete="off"
                    autocapitalize="none"
                    placeholder="08xxxxxxxxxx atau nama@email.com"
                    :value="old('contact')"
                />

                <x-button-primary type="submit">Kirim ulang link tiket</x-button-primary>

                @if ($sent)
                    <x-alert variant="info">Jika data terdaftar, link e-ticket sudah dikirim ulang ke {{ $ticketChannels }} Anda. Cek juga folder spam.</x-alert>
                @endif

                <div class="find-help u-desktop-only-block">{{ $help }}</div>
            </form>

            <div class="find-help find-help--outside u-mobile-only-block">{{ $help }}</div>
        </div>
    </div>
@endsection
