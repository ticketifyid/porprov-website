@extends('layouts.public')

@section('title', 'Pendaftaran penonton')

@section('content')
    <div class="page-shell">
        <div class="home-top">
            <x-header variant="none" :link-logo="false">
                <a href="#cara" class="nav-link">Cara mendaftar</a>
                <a href="{{ url('/cari-tiket') }}" class="nav-link">Cari tiket saya</a>
                <a href="{{ route('daftar') }}" class="btn-primary-sm">Daftar</a>
            </x-header>

            <div class="home-hero">
                <div class="home-hero__copy">
                    <x-logo :size="132" class="u-mobile-only" />

                    <div class="home-hero__text">
                        <div class="eyebrow">Pendaftaran penonton</div>
                        <h1 class="h1-hero">{{ $eventName }}</h1>
                        <div class="tagline-caveat">Satu langkah menuju semangat Jawa Tengah!</div>
                    </div>

                    <div class="home-facts">
                        @if ($eventStartsAt)
                            <div class="home-facts__item">
                                <x-icon name="calendar" :size="22" />
                                <div>{{ $eventStartsAt }}</div>
                            </div>
                        @endif

                        @if ($venue)
                            <div class="home-facts__item">
                                <x-icon name="location" :size="22" />
                                <div>{{ $venue }}</div>
                            </div>
                        @endif

                        <div class="home-facts__item">
                            <x-icon name="ticket" :size="22" />
                            <div class="u-mobile-only">Maksimal 4 tiket per pendaftaran</div>
                            <div class="u-desktop-only">Maks. 4 tiket per pendaftaran</div>
                        </div>
                    </div>

                    <div class="home-actions">
                        <x-button-primary :href="route('daftar')">Daftar sekarang</x-button-primary>
                        <a href="{{ url('/cari-tiket') }}" class="btn-text">Sudah mendaftar? Cari tiket saya</a>
                    </div>
                </div>

                <x-hero-ticket />
            </div>
        </div>

        <div class="home-steps" id="cara">
            <h2 class="home-steps__title">Cara mendaftar</h2>

            <div class="step-list">
                <div class="step-item">
                    <x-step-number :number="1" />
                    <div class="step-item__body">
                        <div class="step-item__title">Isi form pendaftaran</div>
                        <div class="step-item__desc">Nama, domisili, email, nomor WhatsApp, dan jumlah tiket.</div>
                    </div>
                </div>
                <div class="step-item">
                    <x-step-number :number="2" />
                    <div class="step-item__body">
                        <div class="step-item__title">Terima e-ticket</div>
                        <div class="step-item__desc">Link e-ticket berisi QR dikirim ke {{ $ticketChannels }} Anda.</div>
                    </div>
                </div>
                <div class="step-item">
                    <x-step-number :number="3" />
                    <div class="step-item__body">
                        <div class="step-item__title">Tunjukkan QR saat registrasi ulang</div>
                        <div class="step-item__desc">Petugas memindai QR di HP Anda di lokasi acara.</div>
                    </div>
                </div>
                <div class="step-item">
                    <x-step-number :done="true" variant="success" />
                    <div class="step-item__body">
                        <div class="step-item__title">Terima gelang</div>
                        <div class="step-item__desc">Satu QR ditukar sekaligus dengan semua gelang sesuai jumlah tiket.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="site-footer">
            <span>Didukung oleh <strong>Ticketify</strong></span>
            <a href="{{ url('/cari-tiket') }}">Bantuan tiket</a>
        </div>
    </div>
@endsection
