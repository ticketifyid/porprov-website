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

    // Widget Turnstile hanya dirender jika aktif dan site key ada; selama itu
    // tombol Daftar menunggu token (atau captcha cadangan) dari verification.js.
    $turnstileActive = config('services.turnstile.enabled') && filled(config('services.turnstile.site_key'));

    // Panel captcha langsung dibuka jika submit sebelumnya memakai captcha.
    $captchaOpen = $errors->has('captcha') || filled(old('captcha'));
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
                            <div>E-ticket berisi QR dikirim ke {{ $ticketChannels }} Anda.</div>
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

                <form method="POST" action="{{ route('daftar.store') }}" class="form-card" novalidate data-verify-form>
                    @csrf

                    {{-- Honeypot: tidak terlihat dan tidak bisa difokus manusia. --}}
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Situs web</label>
                        <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-card__heading u-desktop-only">
                        <h1 class="h1-page">Form pendaftaran</h1>
                        <div class="form-subtitle">Isi data dengan benar. E-ticket dikirim ke {{ $ticketChannels }} di bawah.</div>
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
                                 :helper="'Boleh diawali 08 atau 62.'.($whatsappActive ? ' E-ticket dikirim ke nomor ini.' : '')" />

                        <div>
                            <x-stepper name="ticket_qty" :max="$maxQty" :value="(int) old('ticket_qty', 1)"
                                       :has-error="$isQuotaError" />

                            @if ($ticketQtyError !== '' && ! $isQuotaError)
                                <div class="field__error">{{ $ticketQtyError }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="form-submit-row">
                        @if ($turnstileActive)
                            <div class="turnstile-box">
                                <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"
                                     data-callback="porprovTurnstileOk"
                                     data-expired-callback="porprovTurnstileExpired"
                                     data-error-callback="porprovTurnstileError"></div>
                            </div>
                        @endif

                        <div class="submit-stack">
                            <x-button-primary type="submit" :disabled="$turnstileActive">Daftar</x-button-primary>

                            @if ($turnstileActive)
                                <div class="verify-wait" data-verify-wait>Menunggu verifikasi keamanan…</div>
                            @endif
                        </div>
                    </div>

                    @error('cf-turnstile-response')
                        <div class="field__error">{{ $message }}</div>
                    @enderror

                    @if ($turnstileActive)
                        <div class="captcha-panel" data-captcha-panel{{ $captchaOpen ? '' : ' hidden' }}>
                            <p class="captcha-panel__hint">Verifikasi keamanan gagal di perangkat ini. Coba aktifkan tanggal dan jam otomatis di HP, atau buka link ini di Chrome/Safari (bukan dari dalam WhatsApp/Instagram). Atau isi kode di bawah ini.</p>

                            <div class="captcha-panel__row">
                                <img class="captcha-panel__image" data-captcha-image data-src="{{ route('daftar.captcha') }}"
                                     alt="Kode verifikasi" width="220" height="70">
                                <button type="button" class="btn-text captcha-panel__refresh" data-captcha-refresh>Ganti gambar</button>
                            </div>

                            <div class="field">
                                <label for="captcha">Kode pada gambar</label>
                                <input type="text" id="captcha" name="captcha" value="" maxlength="5" inputmode="text"
                                       autocomplete="off" autocapitalize="characters" spellcheck="false"
                                       @error('captcha') aria-invalid="true" @enderror>
                                @error('captcha')
                                    <div class="field__error">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="button" class="btn-secondary captcha-panel__retry" data-captcha-retry>Ulangi verifikasi</button>
                        </div>
                    @endif

                    <div class="form-consent u-desktop-only-block">Dengan mendaftar, Anda setuju data digunakan untuk keperluan registrasi acara ini.</div>
                </form>
            </div>

            <div class="form-consent form-consent--page u-mobile-only-block">Dengan mendaftar, Anda setuju data digunakan untuk keperluan registrasi acara ini.</div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($turnstileActive)
        {{-- verification.js HARUS sebelum api.js: callback Turnstile dan
             onerror di bawah memanggil fungsi global dari file ini. --}}
        <script src="@versionedAsset('js/verification.js')"></script>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer onerror="porprovTurnstileLoadFailed()"></script>
    @endif
    <script src="@versionedAsset('js/email-domain.js')"></script>
    <script src="@versionedAsset('js/stepper.js')"></script>
@endpush
