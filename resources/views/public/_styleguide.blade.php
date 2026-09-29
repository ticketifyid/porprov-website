@extends('layouts.public')

@section('title', 'Styleguide')

@push('head')
    <style>
        .sg-note {
            background: #0B1B3F;
            color: #FFFFFF;
            font-size: 13px;
            text-align: center;
            padding: 8px 16px;
        }
        .sg-section {
            padding: 40px 0;
            border-bottom: 1px solid var(--line);
        }
        .sg-section:last-child {
            border-bottom: none;
        }
        .sg-section h2 {
            font-size: 20px;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 20px;
        }
        .sg-label {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--muted);
            margin-bottom: 10px;
        }
        .sg-row {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .sg-row:last-child {
            margin-bottom: 0;
        }
        .sg-box {
            max-width: 420px;
            width: 100%;
        }
        .sg-on-ground {
            background: var(--ground);
            padding: 20px;
            border-radius: 12px;
        }
        .sg-on-navy {
            background: var(--navy);
            padding: 20px;
            border-radius: 12px;
        }
        .sg-card-frame {
            background: var(--ground);
            padding: 24px;
            border-radius: 16px;
            max-width: 480px;
        }
        .sg-ticket-frame {
            background: var(--navy);
            padding: 24px;
            border-radius: 16px;
        }
    </style>
@endpush

@section('content')
    <div class="sg-note">
        Halaman ini hanya tersedia saat APP_ENV=local. Bandingkan tiap bagian dengan artboard di docs/design/artboards/.
    </div>

    <div class="page-container">

        <div class="sg-section">
            <h2>Tipografi</h2>
            <div class="sg-row" style="flex-direction: column; gap: 12px;">
                <div class="eyebrow">Pendaftaran penonton</div>
                <h1 class="h1-hero">Opening Ceremony Porprov Jateng XVII 2026</h1>
                <div class="tagline-caveat">Satu langkah menuju semangat Jawa Tengah!</div>
                <h1 class="h1-page">Form pendaftaran</h1>
                <p>Body text 15/16px, line-height 1.55 — teks paragraf standar untuk seluruh halaman peserta.</p>
            </div>
        </div>

        <div class="sg-section">
            <h2>Strip Porprov</h2>
            <div class="sg-label">4 kolom, tinggi 6px, di puncak setiap halaman</div>
            <x-strip-porprov />
        </div>

        <div class="sg-section">
            <h2>Header</h2>

            <div class="sg-label">Mobile — variant "back" (Form, Cari tiket)</div>
            <div class="sg-card-frame" style="margin-bottom: 24px;">
                <x-header variant="back" :logo-size="84" />
            </div>

            <div class="sg-label">Mobile — variant "logo" (Sukses, Status)</div>
            <div class="sg-card-frame" style="margin-bottom: 24px;">
                <x-header variant="logo" :logo-size="120" />
            </div>

            <div class="sg-label">Desktop — bar penuh (resize browser &ge; 992px untuk melihat)</div>
            <div class="sg-card-frame">
                <x-header variant="none">
                    <a href="#" class="nav-link">Cara mendaftar</a>
                    <a href="#" class="nav-link">Cari tiket saya</a>
                    <a href="#" class="btn-primary-sm">Daftar</a>
                </x-header>
            </div>
        </div>

        <div class="sg-section">
            <h2>Tombol utama &amp; sekunder</h2>
            <div class="sg-row">
                <div class="sg-box">
                    <div class="sg-label">Primary</div>
                    <x-button-primary href="#">Daftar sekarang</x-button-primary>
                </div>
                <div class="sg-box">
                    <div class="sg-label">Primary disabled</div>
                    <x-button-primary :disabled="true">Daftar sekarang</x-button-primary>
                </div>
                <div class="sg-box">
                    <div class="sg-label">Secondary</div>
                    <x-button-secondary href="#">Sudah mendaftar? Cari tiket saya</x-button-secondary>
                </div>
                <div class="sg-box">
                    <div class="sg-label">Text link</div>
                    <a href="#" class="btn-text" style="width: auto; padding: 0;">Kembali ke beranda</a>
                </div>
                <div class="sg-box">
                    <div class="sg-label">Primary small (header/nav)</div>
                    <a href="#" class="btn-primary-sm">Daftar</a>
                </div>
            </div>
        </div>

        <div class="sg-section">
            <h2>Field</h2>
            <div class="sg-row">
                <div class="sg-box">
                    <x-field label="Nama lengkap" name="nama" placeholder="Nama lengkap Anda" />
                </div>
                <div class="sg-box">
                    <x-field label="Domisili" name="regency_id" type="select">
                        <option value="">Pilih kabupaten/kota</option>
                        <option value="1">Kota Semarang</option>
                        <option value="2">Kab. Semarang</option>
                        <option value="99">Luar Jawa Tengah</option>
                    </x-field>
                </div>
                <div class="sg-box">
                    <x-field label="Nomor WhatsApp" name="wa" placeholder="08xxxxxxxxxx"
                              helper="Boleh diawali 08 atau 62. E-ticket dikirim ke nomor ini." />
                </div>
            </div>

            <div class="sg-label">Field email (nama + dropdown domain)</div>
            <div class="sg-row">
                <div class="sg-box">
                    <x-field-email />
                </div>
                <div class="sg-box">
                    <x-field-email domain-value="lainnya" local-value="budi" domain-other-value="kantor.co.id" />
                </div>
            </div>
        </div>

        <div class="sg-section">
            <h2>Stepper jumlah tiket</h2>
            <div class="sg-row">
                <div class="sg-box">
                    <div class="sg-label">Normal (maxQty = 4)</div>
                    <x-stepper name="ticket_qty" :max="4" :value="1" />
                </div>
                <div class="sg-box">
                    <div class="sg-label">Kuota tinggal 2 (coba tekan + sampai mentok)</div>
                    <x-stepper name="ticket_qty_terbatas" :max="2" :value="1" />
                </div>
            </div>
        </div>

        <div class="sg-section">
            <h2>Alert</h2>
            <div class="sg-row" style="flex-direction: column;">
                <div class="sg-box" style="max-width: 100%;">
                    <div class="sg-label">Warn — banner kuota dari server</div>
                    <x-alert variant="warn">
                        <strong>Sisa kuota tinggal 2 tiket.</strong> Silakan kurangi jumlah tiket, lalu tekan Daftar lagi. Data lain tidak perlu diisi ulang.
                    </x-alert>
                </div>
                <div class="sg-box" style="max-width: 100%;">
                    <div class="sg-label">Info — pesan netral cari tiket</div>
                    <x-alert variant="info">
                        Jika data terdaftar, link e-ticket sudah dikirim ulang ke email dan WhatsApp Anda. Cek juga folder spam.
                    </x-alert>
                </div>
            </div>
        </div>

        <div class="sg-section">
            <h2>Badge</h2>
            <div class="sg-row">
                <x-badge variant="success">Sudah ditukar, 14:32</x-badge>
                <x-badge variant="info">Belum ditukar</x-badge>
            </div>
        </div>

        <div class="sg-section">
            <h2>Kartu</h2>
            <div class="sg-row">
                <div class="sg-card-frame">
                    <div class="sg-label">Card sukses</div>
                    <div class="card card--success">
                        <div class="icon-circle icon-circle--success">
                            <x-icon name="check" :size="40" :stroke="3" />
                        </div>
                        <h1 class="h1-page" style="font-size: 26px;">Pendaftaran berhasil</h1>
                        <p style="color: var(--muted);">E-ticket sudah dikirim ke email dan WhatsApp Anda. Jika belum masuk dalam beberapa menit, cek folder spam.</p>
                        <div class="summary-box">
                            <div class="summary-box__col">
                                <span class="summary-box__label">Kode registrasi</span>
                                <span class="summary-box__value">PJT26-7K3M9Q</span>
                            </div>
                            <div class="summary-box__col summary-box__col--right">
                                <span class="summary-box__label">Jumlah</span>
                                <span class="summary-box__value">4 tiket</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sg-card-frame">
                    <div class="sg-label">Card status (belum dibuka / ditutup / kuota penuh)</div>
                    <div class="card card--status">
                        <div class="icon-circle icon-circle--info">
                            <x-icon name="crowd" :size="38" :stroke="2" />
                        </div>
                        <h1 class="h1-page" style="font-size: 25px;">Kuota pendaftaran sudah penuh</h1>
                        <p style="color: var(--muted);">Seluruh tiket Opening Ceremony sudah terdaftar. Jika sudah mendaftar, e-ticket Anda tetap berlaku untuk registrasi ulang.</p>
                        <x-button-secondary href="#">Sudah mendaftar? Cari tiket saya</x-button-secondary>
                    </div>
                </div>
            </div>
        </div>

        <div class="sg-section">
            <h2>Langkah bernomor</h2>
            <div class="sg-label">1 kolom (mobile) &middot; 2 kolom (768px+) &middot; 4 kolom (992px+)</div>
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
                        <div class="step-item__desc">Link e-ticket berisi QR dikirim ke email dan WhatsApp Anda.</div>
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

            <div class="sg-label" style="margin-top: 24px;">Variant "inverse" (dipakai di panel navy desktop, Fase 4)</div>
            <div class="sg-on-navy" style="display: inline-block;">
                <x-step-number :number="1" variant="inverse" />
            </div>
        </div>

        <div class="sg-section">
            <h2>Tiket</h2>
            <div class="sg-label">Resize browser untuk melihat mobile (sobekan horizontal) vs desktop &ge; 992px (sobekan vertikal)</div>
            <div class="sg-ticket-frame">
                <x-ticket-card>
                    <x-slot:badge>
                        <x-badge variant="info">Belum ditukar</x-badge>
                    </x-slot:badge>

                    <x-slot:qr>
                        <div class="qr-frame">
                            <svg width="204" height="204" viewBox="0 0 33 33" shape-rendering="crispEdges" aria-hidden="true">
                                <rect width="33" height="33" fill="#FFFFFF"></rect>
                                <path fill="#0B1B3F" d="M0 0h7v7h-7zM26 0h7v7h-7zM0 26h7v7h-7zM10 10h13v13h-13z"></path>
                            </svg>
                        </div>
                    </x-slot:qr>

                    <x-slot:code>
                        <div class="code-block">
                            <div class="code-block__label">Kode registrasi</div>
                            <div class="code-block__value">PJT26-7K3M9Q</div>
                        </div>
                    </x-slot:code>

                    <x-slot:hint>Tunjukkan QR ini ke petugas registrasi ulang</x-slot:hint>

                    <x-slot:gelang>
                        <div class="gelang-box">
                            <div>
                                <div class="gelang-box__label">Tukar di registrasi ulang</div>
                                <div class="gelang-box__value">4 tiket = 4 gelang</div>
                            </div>
                            <x-icon name="wristband" :size="36" :stroke="1.8" />
                        </div>
                    </x-slot:gelang>

                    <x-slot:details>
                        <dl class="detail-grid">
                            <div>
                                <dt>Nama</dt>
                                <dd>Budi Santoso</dd>
                            </div>
                            <div>
                                <dt>Domisili</dt>
                                <dd>Kota Semarang</dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd>bu***@email.com</dd>
                            </div>
                            <div>
                                <dt>WhatsApp</dt>
                                <dd>0812-****-7890</dd>
                            </div>
                        </dl>
                    </x-slot:details>
                </x-ticket-card>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/email-domain.js') }}"></script>
    <script src="{{ asset('js/stepper.js') }}"></script>
@endpush
