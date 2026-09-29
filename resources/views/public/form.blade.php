@extends('layouts.public')

@section('title', 'Form pendaftaran')

@php
    $ticketQtyError = $errors->first('ticket_qty');
    // Pesan kuota dari server tampil sebagai banner warn di atas form; error
    // validasi biasa (mis. jumlah di luar 1–4) tampil di bawah stepper.
    $isQuotaError = $ticketQtyError !== '' && str_starts_with($ticketQtyError, 'Sisa kuota');

    // Banner memakai pesan aturan 3 apa adanya: kalimat pertama ditebalkan
    // seperti artboard, lalu ditambah satu kalimat khusus banner.
    $quotaLead = $ticketQtyError;
    $quotaTail = '';

    if ($isQuotaError && ($dot = strpos($ticketQtyError, '. ')) !== false) {
        $quotaLead = substr($ticketQtyError, 0, $dot + 1);
        $quotaTail = substr($ticketQtyError, $dot + 2);
    }

    $quotaTail = trim($quotaTail.' Data lain tidak perlu diisi ulang.');
@endphp

@section('content')
    <div class="page-shell">
        <x-header variant="back" :logo-size="84" :back-href="route('home')" :home-href="route('home')">
            <a href="{{ url('/cari-tiket') }}" class="nav-link">Cari tiket saya</a>
        </x-header>

        <div class="page-container form-page">
            <div class="form-heading u-mobile-only">
                <h1 class="h1-page">Form pendaftaran</h1>
                <div class="form-subtitle">{{ $eventName }}</div>
            </div>

            <div class="form-layout">
                <aside class="form-panel">
                    <div class="form-panel__intro">
                        <div class="form-panel__eyebrow">Anda mendaftar untuk</div>
                        <div class="form-panel__title">{{ $eventName }}</div>
                    </div>

                    @if ($eventStartsAt || $venue)
                        <div class="form-panel__facts">
                            @if ($eventStartsAt)
                                <div class="form-panel__fact">
                                    <x-icon name="calendar" :size="20" />
                                    {{ $eventStartsAt }}
                                </div>
                            @endif

                            @if ($venue)
                                <div class="form-panel__fact">
                                    <x-icon name="location" :size="20" />
                                    {{ $venue }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="form-panel__divider"></div>

                    <div class="form-panel__steps">
                        <div class="form-panel__steps-title">Setelah mendaftar</div>

                        <div class="form-panel__step">
                            <x-step-number :number="1" variant="inverse" />
                            <div>E-ticket berisi QR dikirim ke email dan WhatsApp Anda.</div>
                        </div>
                        <div class="form-panel__step">
                            <x-step-number :number="2" variant="inverse" />
                            <div>Tunjukkan QR ke petugas registrasi ulang di lokasi.</div>
                        </div>
                        <div class="form-panel__step">
                            <x-step-number :number="3" variant="inverse" />
                            <div>Terima gelang sesuai jumlah tiket, sekaligus.</div>
                        </div>
                    </div>

                    <x-strip-porprov class="strip-porprov--absolute strip-porprov--panel" />
                </aside>

                <form method="POST" action="{{ route('daftar.store') }}" class="form-card" novalidate>
                    @csrf

                    <div class="form-card__heading u-desktop-only">
                        <h1 class="h1-page">Form pendaftaran</h1>
                        <div class="form-subtitle">Isi data dengan benar. E-ticket dikirim ke email dan WhatsApp di bawah.</div>
                    </div>

                    @error('form')
                        <x-alert variant="warn" role="alert">{{ $message }}</x-alert>
                    @enderror

                    @if ($isQuotaError)
                        <x-alert variant="warn" role="alert"><strong>{{ $quotaLead }}</strong> {{ $quotaTail }}</x-alert>
                    @endif

                    <div class="form-fields">
                        <x-field label="Nama lengkap" name="name" :span2="true" placeholder="Nama lengkap Anda"
                                 autocomplete="name" maxlength="150" value="{{ old('name') }}" />

                        <x-field label="Domisili" name="regency_id" type="select">
                            <option value="">Pilih kabupaten/kota</option>
                            @foreach ($regencies as $regency)
                                <option value="{{ $regency->id }}" @selected((int) old('regency_id') === $regency->id)>{{ $regency->name }}</option>
                            @endforeach
                        </x-field>

                        <x-field-email :local-value="old('email_local', '')"
                                       :domain-value="old('email_domain', 'gmail.com')"
                                       :domain-other-value="old('email_domain_other', '')" />

                        <x-field label="Nomor WhatsApp" name="phone" type="tel" inputmode="numeric"
                                 placeholder="08xxxxxxxxxx" autocomplete="tel" maxlength="20"
                                 value="{{ old('phone') }}"
                                 helper="Boleh diawali 08 atau 62. E-ticket dikirim ke nomor ini." />

                        <div>
                            <x-stepper name="ticket_qty" :max="$maxQty" :value="(int) old('ticket_qty', 1)"
                                       :has-error="$isQuotaError" />

                            @if ($ticketQtyError !== '' && ! $isQuotaError)
                                <div class="field__error">{{ $ticketQtyError }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="form-submit-row">
                        @if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
                            <div class="turnstile-box">
                                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
                            </div>
                        @endif

                        <x-button-primary type="submit">Daftar</x-button-primary>
                    </div>

                    @error('cf-turnstile-response')
                        <div class="field__error">{{ $message }}</div>
                    @enderror

                    <div class="form-consent u-desktop-only-block">Dengan mendaftar, Anda setuju data digunakan untuk keperluan registrasi acara ini.</div>
                </form>
            </div>

            <div class="form-consent form-consent--page u-mobile-only-block">Dengan mendaftar, Anda setuju data digunakan untuk keperluan registrasi acara ini.</div>
        </div>
    </div>
@endsection

@push('scripts')
    @if (config('services.turnstile.enabled') && config('services.turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
    <script src="@versionedAsset('js/email-domain.js')"></script>
    <script src="@versionedAsset('js/stepper.js')"></script>
@endpush
