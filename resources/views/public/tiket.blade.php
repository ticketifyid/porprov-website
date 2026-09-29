@extends('layouts.public')

@section('title', 'E-ticket')
@section('body_class', 'ticket-page')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="page-shell">
        <x-header variant="none">
            <span class="ticket-header-label">E-ticket</span>
        </x-header>

        <div class="ticket-wrap">
            <div class="ticket-hero">
                <div class="ticket-hero__eyebrow u-mobile-only-block">E-ticket</div>
                <h1 class="ticket-hero__title">Opening Ceremony Porprov Jateng XVII 2026</h1>
                @if ($eventStartsAt || $venue)
                    <div class="ticket-hero__when">{{ collect([$eventStartsAt, $venue])->filter()->implode(', ') }}</div>
                @endif
            </div>

            <div class="ticket-card-wrap">
                <x-ticket-card>
                    <x-slot:badge>
                        @if ($isRedeemed)
                            <x-badge variant="success">Sudah ditukar{{ $redeemedAtLabel ? ', '.$redeemedAtLabel : '' }}</x-badge>
                        @else
                            <x-badge variant="info">Belum ditukar</x-badge>
                        @endif
                    </x-slot:badge>

                    <x-slot:qr>
                        <div class="qr-frame {{ $isRedeemed ? 'qr-frame--redeemed' : '' }}" role="img" aria-label="QR code e-ticket">
                            {!! $qrSvg !!}
                            @if ($isRedeemed)
                                <div class="qr-overlay">
                                    <div class="qr-overlay__label">Gelang sudah diterima</div>
                                </div>
                            @endif
                        </div>
                    </x-slot:qr>

                    <x-slot:code>
                        <div class="code-block">
                            <div class="code-block__label">Kode registrasi</div>
                            <div class="code-block__value">{{ $code }}</div>
                        </div>
                    </x-slot:code>

                    <x-slot:hint>Tunjukkan QR ini ke petugas registrasi ulang</x-slot:hint>

                    <x-slot:gelang>
                        <div class="gelang-box">
                            <div>
                                <div class="gelang-box__label">Tukar di registrasi ulang</div>
                                <div class="gelang-box__value">{{ $ticketQty }} tiket = {{ $ticketQty }} gelang</div>
                            </div>
                            <x-icon name="wristband" :size="36" :stroke="1.8" />
                        </div>
                    </x-slot:gelang>

                    <x-slot:details>
                        <dl class="detail-grid">
                            <div>
                                <dt>Nama</dt>
                                <dd>{{ $name }}</dd>
                            </div>
                            <div>
                                <dt>Domisili</dt>
                                <dd>{{ $regencyName }}</dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd>{{ $maskedEmail }}</dd>
                            </div>
                            <div>
                                <dt>WhatsApp</dt>
                                <dd>{{ $maskedPhone }}</dd>
                            </div>
                        </dl>
                    </x-slot:details>
                </x-ticket-card>
            </div>

            <div class="ticket-tips">
                <div class="ticket-tips__item">
                    <x-icon name="sun" :size="20" />
                    <div>
                        <span class="u-desktop-only-inline">Buka halaman ini di HP dan naikkan kecerahan layar saat registrasi ulang.</span>
                        <span class="u-mobile-only-inline">Naikkan kecerahan layar saat menunjukkan QR ke petugas.</span>
                    </div>
                </div>
                <div class="ticket-tips__item">
                    <x-icon name="device" :size="20" />
                    <div>
                        <span class="u-desktop-only-inline">Simpan screenshot sebagai cadangan.</span>
                        <span class="u-mobile-only-inline">Screenshot halaman ini sebagai cadangan jika sinyal di lokasi lemah.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
