@extends('layouts.public')

@section('title', 'Pendaftaran penonton')

@section('content')
    <div class="page-shell">
        <div class="home-top">
            <x-header variant="none">
                <a href="#cara" class="nav-link">Cara mendaftar</a>
                <a href="{{ url('/cari-tiket') }}" class="nav-link">Cari tiket saya</a>
                <a href="{{ route('daftar') }}" class="btn-primary-sm">Daftar</a>
            </x-header>

            <div class="home-hero">
                <div class="home-hero__copy">
                    <div class="home-hero__text">
                        <div class="eyebrow">Pendaftaran penonton</div>
                        <h1 class="h1-hero">{{ $eventName }}</h1>
                        <div class="tagline-caveat">Ngopeni Nglakoni Menuju Puncak Prestasi Jawa Tengah</div>
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

        <div class="home-rules" id="ketentuan">
            <h2 class="home-rules__title">Ketentuan penonton</h2>

            @php
                $lakukan = [
                    ['id-card', 'Membawa kartu identitas'],
                    ['wristband', 'Mengenakan tiket gelang'],
                    ['clock', 'Datang tepat waktu'.($eventStartsTime ? ' (pukul '.$eventStartsTime.')' : '')],
                    ['location', 'Pastikan berada di tribun yang benar'],
                    ['trash', 'Jaga kebersihan'],
                ];
                $jangan = [
                    ['camera', 'Membawa kamera profesional dan tongsis'],
                    ['cigarette', 'Merokok atau rokok elektrik'],
                    ['pill', 'Membawa obat-obatan terlarang'],
                    ['blade', 'Membawa senjata tajam'],
                    ['paw', 'Membawa hewan peliharaan'],
                ];
            @endphp

            <div class="rules-grid">
                @foreach ([['do', 'check', 'Lakukan', $lakukan], ['dont', 'close', 'Jangan', $jangan]] as [$kind, $badge, $title, $items])
                    <section class="rules-card rules-card--{{ $kind }}">
                        <h3 class="rules-card__title"><span class="step-number step-number--{{ $kind === 'do' ? 'success' : 'danger' }}"><x-icon :name="$badge" :size="18" :stroke="3" /></span>{{ $title }}</h3>
                        <ul class="rules-list">
                            @foreach ($items as [$icon, $text])
                                <li class="rules-list__item">
                                    <x-icon :name="$icon" :size="22" />
                                    <span>{{ $text }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>

            <a href="@versionedAsset('img/poster-dodont.webp')" data-fallback="@versionedAsset('img/poster-dodont.jpg')"
               target="_blank" rel="noopener" class="btn-text rules-poster-link" data-poster-link>Lihat poster lengkap</a>
        </div>

        <div class="site-footer">
            <span>Didukung oleh <strong>Ticketify</strong></span>
            <a href="{{ url('/cari-tiket') }}">Bantuan tiket</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Poster WebP; browser tanpa dukungan WebP dialihkan ke JPG.
        (function () {
            var link = document.querySelector('[data-poster-link]');
            var canvas = document.createElement('canvas');
            var supported = canvas.getContext && canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
            if (link && !supported) { link.href = link.getAttribute('data-fallback'); }
        })();
    </script>
@endpush
