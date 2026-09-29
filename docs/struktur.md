# Struktur folder

Struktur final yang disepakati untuk proyek ini. Ikuti persis; jika perlu menyimpang, tanya dulu.

Catatan penting:
- `public/metronic/` BELUM ada di repo saat ini; disiapkan pemilik proyek sebelum Fase 2. Jangan membuat placeholder-nya sendiri.
- Migration `users` bawaan Laravel DIEDIT LANGSUNG (bukan migration baru) supaya kolomnya persis sesuai `docs/erd.md`: `username`, `role`, `is_active`, tanpa `email`. Migration `password_reset_tokens` DIHAPUS dari file yang sama (tidak ada reset password publik).
- Logika transaksi kuota WAJIB berada di `app/Actions/RegisterAttendee.php` — bukan di controller atau model.
- Logika redeem WAJIB berada di `app/Actions/RedeemRegistration.php`, dipakai oleh ketiga jalur: hardware (langsung redeem), konfirmasi kamera, dan pencarian manual.
- Helper murni (`PhoneNormalizer`, `RegistrationCodeGenerator`, `EmailCanonicalizer`) tinggal di `app/Support/`, bukan `app/Services/`.

### Penyimpangan disetujui pada Fase 2

- `app/Enums/UserRole.php` (folder `Enums/` baru, tidak ada di struktur awal): enum `admin`/`scanner` dengan `label()`, dipakai sebagai cast pada `User::role` dan oleh `EnsureRole`/`User::isAdmin()`/`User::isScanner()`.
- Validasi login (`LoginController@login`) dilakukan inline lewat `$request->validate(...)`, BUKAN FormRequest terpisah — tidak ada `Requests/Auth/` supaya tidak menyimpang dari daftar `Requests/` di bawah (hanya `Public/` dan `Admin/`).
- `tests/Feature/AuthTest.php` dan `tests/Feature/AccessControlTest.php` (tidak tercantum di daftar `tests/` awal): menguji login, throttle, akun nonaktif, dan gating role per rute.
- Tidak ada `layouts/scanner.blade.php` terpisah: halaman `scanner/index.blade.php` memakai `layouts/admin.blade.php` yang sama dengan halaman admin (sidebar Metronic, menu disaring per role). Lihat catatan Fase 7 di bawah — ini perlu ditinjau ulang saat hasil scan harus tampil besar di layar HP.

### Penyimpangan disetujui pada Fase 3

- `public/img/` (folder baru, tidak ada di struktur awal): berisi `logo-porprov.png`, dipakai oleh komponen `x-logo`. Sesuai catatan di `docs/design/DESIGN.md`, file ini masih potongan resolusi rendah dan harus diganti logo asli panitia sebelum go-live.
- `public/js/` diisi dua file dari daftar di bawah: `stepper.js` (aturan stepper DESIGN.md) dan `email-domain.js` (pecah-otomatis email yang di-paste + pratinjau "Email lengkap"). `scanner-hardware.js` dan `scanner-camera.js` baru dibuat pada Fase 7.
- `resources/views/components/` diisi 14 Blade component (daftar lengkap, menggantikan "dst" pada struktur di bawah):
  `strip-porprov`, `header` (varian `back` / `logo` / `none`), `logo`, `icon` (satu-satunya sumber SVG garis inline), `button-primary`, `button-secondary`, `field`, `field-email`, `stepper`, `alert` (`warn` / `info`), `badge` (`success` / `info`), `step-number` (`default` / `success` / `inverse`), `ticket-card`, `ticket-perforation` (`horizontal` / `vertical`).

### Penyimpangan disetujui pada Fase 4

- `App\Jobs\SendTicketNotification` sudah dibuat di Fase 4, tapi masih kerangka: constructor `(int $registrationId, string $channel)` dan `handle()` kosong. Alasannya pendaftaran wajib men-dispatch job ini setelah COMMIT (aturan 8), sedangkan isinya (panggil `TicketNotifier`, perbarui `notification_logs`, retry) baru dikerjakan pada Fase 5.
- `RegistrationController` juga melayani `GET /daftar/sukses/{token}` (`success()`), bukan hanya `POST /daftar`; halaman sukses menampilkan data registrasi, jadi ditempatkan bersama controller pendaftaran alih-alih `HomeController`.
- Verifikasi Cloudflare Turnstile dilakukan di dalam `StoreRegistrationRequest::withValidator()` (bukan kelas `app/Rules/`), supaya tidak menambah folder di luar struktur ini. Konfigurasi di `config/services.php` → `turnstile.site_key` / `turnstile.secret_key` / `turnstile.enabled` (`TURNSTILE_*` di `.env`); `phpunit.xml` mengisi `TURNSTILE_ENABLED=false` supaya verifikasi dilewati saat tes.
- `resources/views/components/hero-ticket.blade.php` + `public/img/hero-qr.svg`: ilustrasi e-ticket pada hero beranda desktop (`DesktopMain.dc.html`). SVG QR-nya hiasan statis dari artboard, bukan QR registrasi.
- `tests/Feature/ExampleTest.php` dan `resources/views/welcome.blade.php` (sisa scaffold Laravel) dihapus karena `/` sekarang dilayani `HomeController`.
- `public/css/app.css` menambah dua token yang dipakai panel navy form desktop tapi tidak ada di tabel token `DESIGN.md`: `--on-navy-strong: #E3EAF9` (teks fakta acara) dan `--navy-line: #2B4B8E` (garis pemisah). Nilainya diambil persis dari `DesktopForm.dc.html`.
- Teks banner kuota di `form.blade.php` mengikuti aturan 3 `CLAUDE.md`, bukan artboard: frasa ", lalu tekan Daftar lagi" dihilangkan. Banner menampilkan pesan error `ticket_qty` **apa adanya** ("Sisa kuota tinggal {sisa} tiket. Silakan kurangi jumlah tiket."), kalimat pertamanya ditebalkan seperti artboard, lalu ditambah kalimat kedua "Data lain tidak perlu diisi ulang." Kalimat kedua itu hanya ada di banner; pesan error field `ticket_qty` (dan isi session) tetap persis kalimat aturan 3.
- Halaman Status membedakan "belum dibuka" dan "ditutup" begini: `ditutup` jika `registration_close_at` sudah lewat, ATAU jika `is_open = false` sedangkan `registration_open_at` sudah lewat (penutupan manual di tengah masa pendaftaran). Selain itu `belum dibuka`. Jika `registration_open_at` masih kosong, kalimat "Pendaftaran dibuka pada [TANGGAL & JAM BUKA]" dilewati (tidak ada placeholder yang tampil ke peserta).
- Tautan `/cari-tiket` dan `/tiket/{token}` ditulis dengan `url()`, bukan `route()`, karena rutenya baru dibuat pada Fase 6.
- Penanganan exception di `RegistrationController@store` dibagi tiga, dari yang paling spesifik:
    1. `UniqueConstraintViolationException` (bentrok `(event_id, email_canonical)` di level DB saat dua submit balapan) → `back()->withInput()` dengan error di field email: "Email ini sudah terdaftar. Gunakan menu Cari tiket saya untuk menerima ulang e-ticket."
    2. `QueryException` dengan kode MySQL 1205 (lock wait timeout) atau 1213 (deadlock) — bisa terjadi saat war kuota karena `lockForUpdate()` — → `report($e)` lalu `back()->withInput()` dengan banner warn (error bag `form`): "Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi." Kodenya dibaca dari `$e->errorInfo[1]`.
    3. `QueryException` lain sengaja TIDAK ditangkap; biarkan jadi 500 supaya masalah sebenarnya terlihat di log.
  Kunci error bag `form` dipakai untuk banner yang tidak terikat satu field; `form.blade.php` merendernya sebagai `x-alert variant="warn"` di atas banner kuota.
- Domain email selalu diperlakukan huruf kecil dan sudah di-trim, di tiga tempat sekaligus (mengikuti logika artboard `Form.dc.html`):
    - `public/js/email-domain.js`: domain hasil paste/autofill di kotak nama di-lowercase + trim SEBELUM dicocokkan dengan daftar, jadi `Budi.Santoso+1@Gmail.com` memilih `gmail.com` di dropdown, bukan jatuh ke "Lainnya…". Isi kotak "Lainnya…" juga disimpan huruf kecil (posisi kursor dijaga) dan di-trim saat blur.
    - `StoreRegistrationRequest::prepareForValidation()`: `email_domain` di-lowercase + trim; kalau `email_domain = lainnya` sedangkan `email_domain_other` ternyata ada di daftar domain, keduanya dinormalkan menjadi pilihan dropdown biasa (`email_domain = <domain>`, `email_domain_other` kosong).
    - Komponen `x-field-email` melakukan normalisasi yang sama saat menampilkan ulang nilai `old()`, supaya form yang kembali karena error validasi menampilkan dropdown, bukan "Lainnya…". Ini perlu karena Laravel mem-flash input dari request asli, bukan hasil `merge()` di FormRequest.
- Cache busting aset statis tanpa build: `app/Support/AssetVersion.php` + Blade directive `@versionedAsset('css/app.css')` yang didaftarkan di `AppServiceProvider::boot()`. Hasilnya `asset($path).'?v='.filemtime(public_path($path))`, dengan memo per request; kalau filenya tidak ada, URL tetap dihasilkan tanpa query string (tidak melempar exception). Ini menjadi **aturan 14 di `CLAUDE.md`**: semua CSS/JS milik proyek wajib lewat directive ini, termasuk di halaman admin/scanner berlayout Metronic (mis. `scanner-hardware.js` dan `scanner-camera.js` nanti di Fase 7); hanya file di `public/metronic/` yang memakai `asset()` biasa. Saat ini dipakai di `css/app.css` (`layouts/public.blade.php`) serta `js/email-domain.js` dan `js/stepper.js` (`form.blade.php`, `_styleguide.blade.php`).
  `AssetVersionTest::test_semua_css_dan_js_proyek_memakai_versioned_asset` memindai seluruh file Blade dan gagal kalau ada `asset('css/...')`/`asset('js/...')` yang belum diganti, atau sebaliknya `@versionedAsset('metronic/...')`.
  Catatan: `AssetVersion` melanggar aturan "Support/ hanya untuk helper murni tanpa dependency Laravel" karena butuh `asset()` dan `public_path()`. Ditempatkan di sana supaya semua helper tetap satu folder, alih-alih membuka folder baru untuk satu kelas.
- `StoreRegistrationRequest::getRedirectUrl()` di-override menjadi `previous(route('daftar'))` supaya error validasi selalu kembali ke form meski browser tidak mengirim header `Referer` (tanpa ini pengguna terlempar ke beranda dan pesan errornya tidak terlihat).

```
app/
  Actions/
    RegisterAttendee.php              # transaksi kuota: lockForUpdate, hitung sisa, increment, create registration
    RedeemRegistration.php            # update bersyarat WHERE id=? AND redeemed_at IS NULL, catat scan_logs
  Support/
    PhoneNormalizer.php               # aturan 5 CLAUDE.md
    RegistrationCodeGenerator.php     # aturan 6 CLAUDE.md
    EmailCanonicalizer.php            # aturan 13 CLAUDE.md
    AssetVersion.php                  # cache busting ?v=filemtime, ditambahkan Fase 4 (lihat catatan)
  Enums/
    UserRole.php                      # admin / scanner, ditambahkan Fase 2 (lihat catatan di atas)
  Contracts/
    TicketNotifier.php
  Notifications/
    LogTicketNotifier.php             # implementasi default (TICKET_NOTIFIER=log)
  Http/
    Controllers/
      Public/
        HomeController.php            # / , /daftar (GET)
        RegistrationController.php    # POST /daftar, panggil RegisterAttendee
        TicketController.php          # /tiket/{token}, /cari-tiket
      Scanner/
        ScanController.php            # POST /scan, /scan/{registration}/redeem, panggil RedeemRegistration
      Admin/
        DashboardController.php
        EventController.php           # jadwal & kuota
        RegistrationAdminController.php # pencarian, detail, batal, kirim ulang
        UserController.php            # akun petugas
        ExportController.php
      Auth/
        LoginController.php
    Requests/
      Public/
        StoreRegistrationRequest.php  # prepareForValidation: rakit email, hitung email_canonical, ticket_qty
        SearchTicketRequest.php
      Admin/
        UpdateEventRequest.php
        StoreUserRequest.php
    Middleware/
      EnsureUserIsActive.php
      EnsureRole.php                  # admin / scanner
  Exceptions/
    QuotaException.php
  Jobs/
    SendTicketNotification.php        # (registrationId, channel), panggil TicketNotifier
  Models/
    Event.php
    Regency.php
    Registration.php
    NotificationLog.php
    User.php
    ScanLog.php

database/
  migrations/
    ..._create_events_table.php
    ..._create_regencies_table.php
    ..._create_registrations_table.php   # termasuk email, email_canonical, index sesuai erd.md
    ..._create_notification_logs_table.php
    ..._create_users_table.php           # migration bawaan DIEDIT langsung: username, role, is_active, tanpa email; password_reset_tokens DIHAPUS dari sini
    ..._create_scan_logs_table.php
  seeders/
    EventSeeder.php
    RegencySeeder.php
    AdminUserSeeder.php
    DatabaseSeeder.php

resources/
  views/
    layouts/
      public.blade.php                  # font Plus Jakarta Sans + Caveat
      admin.blade.php                   # Metronic (dipasang setelah Fase 2, lihat catatan di atas)
    components/                         # strip-porprov, header, button-primary, field, stepper, ticket-card, dst
    public/
      home.blade.php
      form.blade.php
      sukses.blade.php
      status.blade.php
      tiket.blade.php
      cari-tiket.blade.php
      _styleguide.blade.php             # APP_ENV=local only
    scanner/
      index.blade.php
    admin/
      dashboard.blade.php
      events/
      registrations/
      users/

public/
  css/app.css
  img/                                  # logo-porprov.png, hero-qr.svg (hiasan hero desktop)
  metronic/                             # disiapkan pemilik proyek sebelum Fase 2
  js/
    stepper.js
    email-domain.js
    scanner-hardware.js
    scanner-camera.js

routes/
  web.php                               # public + admin + scanner, digroup middleware
  console.php                           # Schedule::command(queue:work ...)

tests/
  Unit/
    PhoneNormalizerTest.php
    RegistrationCodeGeneratorTest.php
    EmailCanonicalizerTest.php
  Feature/
    AuthTest.php                        # login, throttle, akun nonaktif — ditambahkan Fase 2
    AccessControlTest.php               # gating role admin/scanner per rute — ditambahkan Fase 2
    AssetVersionTest.php                # cache busting ?v=filemtime — ditambahkan Fase 4
    RegistrationTest.php                # kuota, duplikat email_canonical, HP tidak unik, format
    NotificationJobTest.php
    TicketPageTest.php
    ScanTest.php
    Admin/
      EventSettingsTest.php
      RegistrationAdminTest.php
```

## Catatan untuk Fase 7 (scanner)

Fase 2 memakai `layouts/admin.blade.php` (sidebar + header Metronic) apa adanya untuk `scanner/index.blade.php`, karena di fase itu halamannya masih kosong. Di Fase 7, hasil scan harus tampil **besar dan kontras** (hijau "SERAHKAN N GELANG", merah "SUDAH DITUKAR", abu "QR TIDAK DIKENAL") dan dipakai petugas dari HP di lapangan — sidebar Metronic akan memakan ruang layar dan mengganggu fokus. Saat mengerjakan Fase 7, tinjau ulang apakah `scanner/index.blade.php` perlu tampilan fokus tanpa sidebar (mis. varian `class="app-blank"` seperti `layouts/auth.blade.php`, header tipis berisi nama petugas + tombol keluar saja) alih-alih tetap mewarisi `layouts/admin.blade.php` penuh.

## Alasan pemisahan

- Tidak ada `Repositories/` atau `Services/` untuk logika bisnis inti — `Actions/` sudah cukup tipis dan eksplisit untuk dua operasi paling kritis (registrasi dan redeem), tanpa lapisan abstraksi tambahan.
- `Support/` khusus helper murni tanpa state dan tanpa dependency Laravel selain fungsi bawaan PHP — mudah diuji sebagai unit test murni.
- `Notifications/` (bukan `Services/Notifications/`) menyimpan implementasi `TicketNotifier`; implementasi asli (Mail, HTTP ke VPS) masuk folder yang sama di Fase 10 tanpa mengubah controller/job.
- Controller publik, scanner, dan admin dipisah namespace supaya middleware role jelas dan rute mudah digrup di `web.php`.
