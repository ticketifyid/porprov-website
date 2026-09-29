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

### Penyimpangan disetujui pada Fase 5

- `LogTicketNotifier` ditaruh di `app/Services/Notifications/`, bukan `app/Notifications/` seperti struktur awal — `app/Notifications/` adalah folder bawaan Laravel untuk kelas Notification, dan implementasi `TicketNotifier` bukan itu. Daftar struktur dan bagian "Alasan pemisahan" di bawah sudah disesuaikan; implementasi asli Fase 10 juga masuk ke folder yang sama.
- File Fase 5 lainnya sudah tercantum di struktur di bawah (`Contracts/TicketNotifier.php`, `Jobs/SendTicketNotification.php`, `tests/Feature/NotificationJobTest.php`).
- Binding `TicketNotifier` ada di `AppServiceProvider::register()` (bukan `boot()`) lewat `match` atas `config('services.ticket_notifier')`. Driver yang tidak dikenal **melempar `InvalidArgumentException`**, bukan jatuh ke `LogTicketNotifier`: salah ketik `TICKET_NOTIFIER` di produksi harus gagal keras, bukan berakhir "semua log berstatus sent tapi tidak ada peserta yang menerima".
- `SendTicketNotification`: `$tries = 3`, `$backoff = [60, 300]` detik. Saat gagal, job menaikkan `attempts` + mengisi `last_error` lalu **melempar ulang exception-nya** — retry dan penyerahan diurus queue, bukan job. Penanda `status = 'failed'` dipasang di `failed()`. Kanal divalidasi di awal `handle()` sebelum menyentuh database (kanal salah = salah kode, tidak boleh sempat membuat baris `notification_logs`), dan log yang sudah `sent` tidak dikirim ulang (idempoten, karena worker bisa mati setelah kirim tapi sebelum job dihapus dari antrean).
- `docs/notifikasi.md` (dokumen baru, tidak ada di daftar dokumen awal `CLAUDE.md`): panduan untuk pemilik proyek di Fase 10 — kontrak interface, cara menambah implementasi, perilaku retry, cara mengetes, cara memeriksa di produksi.
- `config/services.php` → `ticket_notifier` (`TICKET_NOTIFIER` di `.env`, default `log`), sejajar blok `turnstile` dari Fase 4.
- `routes/console.php` diisi `Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5)` seperti `docs/hosting.md`, dengan satu tambahan: kunci `withoutOverlapping` dibatasi **5 menit**, bukan default 24 jam. Shared hosting biasa membunuh proses yang dianggap terlalu lama, dan proses yang mati tidak sempat melepas kuncinya — dengan default, satu worker yang dibunuh membuat seluruh notifikasi berhenti sampai kuncinya kedaluwarsa keesokan harinya.
- Efek samping yang diterima: `RegistrationTest` yang tidak memakai `Queue::fake()` menjalankan job secara sinkron (`QUEUE_CONNECTION=sync` di `phpunit.xml`), jadi `LogTicketNotifier` ikut menulis ke `storage/logs/laravel.log` saat tes. Dibiarkan karena isinya bukan data rahasia dan mematikannya butuh binding khusus di tes.

### Penyimpangan disetujui pada Fase 6

- Package baru `chillerlan/php-qrcode:^6.0` (disetujui pemilik proyek; `php: ^8.2`, tanpa Imagick/GD). QR dirender inline sebagai SVG di `TicketController::qrSvg()` dari `token` (bukan kode registrasi, aturan 7). Di v6 output default sudah SVG; warna modul diatur lewat CSS (`.qr-frame .qr-svg .dark`), bukan `moduleValues`.
- Masking email (`bu***@domain`) dan nomor (`0812-****-7890`) adalah method private di `TicketController`, bukan helper `Support/` baru. Halaman tiket hanya menerima string yang sudah disamarkan; email/nomor utuh tidak pernah sampai ke view.
- `TicketController::show` mengikat model lewat `{registration:token}` (kolom `token`, tanpa mengubah `getRouteKeyName()`); token tak dikenal otomatis 404. Halaman ini mengirim `<meta name="robots" content="noindex, nofollow">`, `<meta name="referrer" content="no-referrer">`, dan header `X-Robots-Tag: noindex`. Tanggal/lokasi acara disembunyikan jika `event_starts_at`/`venue` masih null (tanpa placeholder), sama seperti Fase 4.
- Throttle `/cari-tiket` dua lapis: (1) per IP `throttle:20,1` di rute, satu-satunya yang boleh menghasilkan 429 (CGNAT membuat banyak pengguna seluler berbagi IP); (2) per kontak ternormalisasi (`email_canonical` atau `62xxx`) maksimal 3 per jam, lewat `RateLimiter` di `TicketController@search`. Kalau batas kontak terlampaui, respons TETAP pesan netral yang sama persis; hanya dispatch yang dilewati (aturan 11).
- Kirim ulang lewat `/cari-tiket` mereset `notification_logs` (email + whatsapp) ke `pending` lalu men-dispatch job. Reset wajib karena `SendTicketNotification` sengaja idempoten dan melewati log berstatus `sent`. Untuk pencarian by HP, SEMUA registrasi yang cocok dikirim ulang.
- Zona waktu: `config/app.php` → `timezone` kini `env('APP_TIMEZONE', 'UTC')`, dan `APP_TIMEZONE=Asia/Jakarta` diisi di `.env` dan `.env.example`. Jam pada badge "Sudah ditukar" tampil WIB. `registration_open_at`/`close_at`/`event_starts_at` disimpan dan dibaca dalam zona yang sama (kolom DATETIME MySQL tanpa info zona), jadi **data jadwal yang sudah tersimpan sebelum perubahan ini (diisi dalam UTC) bergeser 7 jam** dan harus diisi ulang lewat halaman admin. Seluruh tes lulus dengan zona WIB, termasuk logika jadwal pendaftaran Fase 4.
- `public/css/app.css` menambah bagian "Fase 6" (halaman tiket berlatar navy, cari tiket, utilitas `u-*-only-inline`). Semua nilai dikutip dari artboard `Tiket`, `DesktopTiket`, `LupaTiket`, `DesktopLupaTiket`.

### Penyimpangan disetujui pada Fase 7

- **Tampilan fokus scanner** (menjawab "Catatan untuk Fase 7" di bawah): `resources/views/layouts/scanner.blade.php` (baru) memakai Metronic `app-blank` tanpa sidebar/toolbar, hanya header tipis berisi nama + role petugas, tombol suara, link Dashboard (khusus admin), dan tombol Keluar. `scanner/index.blade.php` tidak lagi mewarisi `layouts/admin.blade.php`.
- **`app/Enums/ScanResult.php`**: enum `success` / `already_redeemed` / `not_found`, cermin enum kolom `scan_logs.result`. Nilai balik `RedeemRegistration` dan isi payload JSON.
- **`RedeemRegistration` punya tiga method**, bukan satu: `handle()` (update bersyarat + log), `logMiss()` (QR tidak dikenal, `registration_id` null), `logAlreadyRedeemed()` (langkah pertama kamera/manual atas tiket yang sudah tertukar). Alasannya semua penulisan `scan_logs` harus tetap di satu kelas, termasuk percobaan yang tidak meng-update apa pun.
- **Langkah pertama kamera tidak menulis `scan_logs`** kalau tiketnya masih valid dan belum ditukar. Enum `result` hanya mengenal `success`/`already_redeemed`/`not_found`, dan `success` harus berarti gelang benar-benar diserahkan — jadi yang tercatat untuk jalur kamera adalah konfirmasinya. Percobaan yang berakhir `not_found` atau `already_redeemed` tetap dicatat di langkah pertama.
- **Respons JSON, bukan redirect.** `POST /scan`, `POST /scan/cari`, dan `POST /scan/{registration}/redeem` membalas JSON; panel hasil digambar `public/js/scanner.js`. Alasannya kamera tidak boleh ter-reload (html5-qrcode harus di-init ulang setiap reload) dan ketiga jalur jadi memakai satu gaya yang sama. Nilai `result` yang dikenal klien: `success`, `already_redeemed`, `not_found`, `pending_confirm` (khusus langkah pertama kamera), `candidates` dan `too_short` (khusus pencarian manual).
- **Rute tambahan `POST /scan/cari`** (`ScanController@search`) untuk langkah pertama pencarian manual — bentuk responsnya daftar kandidat, bukan hasil satu tiket, jadi dipisah dari `POST /scan`.
- **Payload JSON hanya data layar petugas**: id, kode, nama, kab/kota, jumlah tiket, status + jam + nama petugas penukar. `token`, email, dan nomor HP utuh tidak pernah dikirim ke klien.
- **Validasi inline** di `ScanController` lewat `$request->validate(...)`, mengikuti preseden `LoginController` di Fase 2 — tidak ada `Requests/Scanner/`.
- **`RegistrationCodeGenerator::normalizeForSearch()`** (bukan helper `Support/` baru): uppercase, buang semua karakter selain `0-9A-Z` (termasuk spasi dan tanda hubung), `O`→`0`, `I`/`L`→`1`. Dibandingkan di database dengan `REPLACE(code, '-', '')` supaya `pjt26 7ok3m9` menemukan `PJT26-70K3M9`. Ditaruh di kelas itu karena aturan substitusi itu memang milik alfabet Crockford Base32 (aturan 6).
- **Aset baru**: `public/css/scanner.css` (panel hasil besar/kontras; tidak memakai token `docs/design/DESIGN.md` karena dokumen itu menyatakan halaman scanner memakai Metronic), `public/js/scanner.js` (inti: mode, fetch, panel, pencarian manual, umpan balik), `public/js/scanner-camera.js`, `public/js/scanner-hardware.js`, dan `public/js/vendor/html5-qrcode.min.js`. Semuanya dimuat lewat `@versionedAsset` (aturan 14).
- **html5-qrcode di-self-host** (`public/js/vendor/html5-qrcode.min.js`, v2.3.8, di-commit) alih-alih CDN: tidak ada daftar "CDN yang diizinkan" di dokumen mana pun, dan hari H tidak boleh bergantung pada domain pihak ketiga. Urutan `<script>` di layout: vendor → `scanner-camera.js` → `scanner-hardware.js` → `scanner.js` terakhir, karena `scanner.js` memanggil `init()` dan menerapkan mode terakhir (localStorage) lewat event `scanner:mode` yang listener-nya harus sudah terpasang.
- **Timeout fetch 8 detik** lewat `AbortController` di `scanner.js`. Timeout atau error jaringan → panel abu-abu "Koneksi gagal. Silakan scan ulang."; flag `busy` selalu dilepas di `finally` supaya satu kegagalan tidak membuat halaman berhenti menerima scan.
- **Penanda `recent_self`**: kalau `already_redeemed` sedangkan `redeemed_by` adalah petugas yang sedang login DAN `redeemed_at` kurang dari 2 menit lalu, JSON menambah `recent_self: true` dan panel tampil netral ("Baru saja Anda tukar pukul HH.MM (N gelang)"), bukan merah. Alat scanner kadang mengirim dua kali dan petugas kadang memindai ulang karena ragu; itu bukan indikasi tiket ganda. `scan_logs.result` tetap `already_redeemed`.
- **Input berkecepatan alat menang atas kotak pencarian manual**: `docs/arsitektur.md` menulis "listener keyboard mengabaikan event dari `input`/`textarea`". Di mode alat scanner, yang diabaikan hanyalah ketikan berkecepatan manusia — rentetan dengan jeda antar karakter < 50 ms, panjang 48, diakhiri `Enter` tetap diperlakukan sebagai scan meski fokus ada di kotak pencarian; kotak pencarian dikosongkan dan pencariannya tidak dikirim. Tanpa ini petugas yang lupa memindahkan fokus akan "kehilangan" scan-nya ke dalam kotak teks.
- **Umpan balik suara + getar**: nada pendek lewat Web Audio API (oscillator, tanpa file audio) dan `navigator.vibrate`, dengan pola berbeda untuk `success`, `already_redeemed`, `not_found`, dan gagal koneksi. Tombol "Suara: nyala/mati" di header, pilihannya disimpan di `localStorage` (`scanner.sound`, sejalan dengan `scanner.mode`). Semua pemanggilannya dibungkus `try/catch` karena browser bisa menolak audio sebelum ada interaksi pengguna.
- **Pencarian manual nama minimal 3 karakter**, divalidasi di server (`ScanController::MIN_NAME_LENGTH`): di bawah itu responsnya `{result: 'too_short', message: 'Ketik minimal 3 karakter nama.'}` tanpa hasil dan tanpa baris `scan_logs`. Input dianggap nomor HP bila berisi ≥ 7 digit, dan kandidat dibatasi 10 baris supaya layar HP tetap terbaca.
- **Pemaksaan HTTPS bukan bagian fase ini.** `docs/arsitektur.md` mewajibkan halaman scanner memakai HTTPS; yang ada sekarang hanya peringatan di halaman kalau request tidak `secure()` (kamera memang tidak akan diizinkan browser). Konfigurasi HTTPS-nya masuk `docs/deploy.md` di Fase 9.
- **Proxy dipercaya HANYA di `APP_ENV=local`** (`bootstrap/app.php`): `if (env('APP_ENV') === 'local') { $middleware->trustProxies(at: '*'); }`. Dibutuhkan untuk menguji kamera dari HP lewat Cloudflare Tunnel — tunnel menerima HTTPS di sisi luar lalu meneruskan `http://` + `X-Forwarded-Proto`, jadi tanpa ini URL aset tetap `http://` dan browser menolak membuka kamera. **Tidak boleh aktif di environment lain**: mempercayai semua proxy berarti `X-Forwarded-For` apa pun dipercaya, sehingga throttle per IP (`POST /daftar`, `POST /cari-tiket`) bisa ditembus dengan memalsukan header. Kondisinya memakai `env()` dan bukan `app()->environment()` karena callback `withMiddleware` berjalan saat kernel HTTP di-resolve, sebelum konfigurasi dimuat; efek sampingnya aman — di server yang memakai `config:cache`, `.env` tidak dimuat sehingga nilainya null dan proxy tetap tidak dipercaya. Dijaga `tests/Feature/TrustedProxyTest.php` (baru, tidak ada di daftar `tests/` awal): di `APP_ENV=testing`, header `X-Forwarded-Proto`/`For`/`Host` harus diabaikan.
- `tests/Feature/ScanTest.php` menutup: kamera tidak mengubah status, konfirmasi kamera, hardware langsung redeem, redeem kedua (`redeemed_at` tidak berubah), `recent_self` (petugas sama < 2 menit vs petugas lain / > 2 menit), `not_found`, normalisasi kode manual, pencarian nama/HP multi-kandidat, nama < 3 karakter, petugas nonaktif, guest, dan admin.

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
    ScanResult.php                    # success / already_redeemed / not_found, ditambahkan Fase 7
  Contracts/
    TicketNotifier.php
  Services/
    Notifications/
      LogTicketNotifier.php           # implementasi default (TICKET_NOTIFIER=log)
  Http/
    Controllers/
      Public/
        HomeController.php            # / , /daftar (GET)
        RegistrationController.php    # POST /daftar, panggil RegisterAttendee
        TicketController.php          # /tiket/{token}, /cari-tiket
      Scanner/
        ScanController.php            # GET /scanner, POST /scan, /scan/cari, /scan/{registration}/redeem; panggil RedeemRegistration
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
      scanner.blade.php                 # Metronic app-blank tanpa sidebar, ditambahkan Fase 7
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
  css/
    app.css
    scanner.css                         # panel hasil scan, ditambahkan Fase 7
  img/                                  # logo-porprov.png, hero-qr.svg (hiasan hero desktop)
  metronic/                             # disiapkan pemilik proyek sebelum Fase 2
  js/
    stepper.js
    email-domain.js
    scanner.js                          # inti halaman scanner (Fase 7)
    scanner-hardware.js
    scanner-camera.js
    vendor/
      html5-qrcode.min.js               # v2.3.8, di-self-host (bukan CDN)

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
    TrustedProxyTest.php                # X-Forwarded-* hanya dipercaya di local — ditambahkan Fase 7
    RegistrationTest.php                # kuota, duplikat email_canonical, HP tidak unik, format
    NotificationJobTest.php
    TicketPageTest.php
    ScanTest.php
    Admin/
      EventSettingsTest.php
      RegistrationAdminTest.php
```

## Catatan untuk Fase 7 (scanner) — SUDAH DIJAWAB

Keputusannya: `scanner/index.blade.php` memakai `layouts/scanner.blade.php` baru (Metronic `app-blank`, tanpa sidebar, header tipis). Lihat "Penyimpangan disetujui pada Fase 7" di atas. Catatan aslinya disimpan di bawah sebagai riwayat.

Fase 2 memakai `layouts/admin.blade.php` (sidebar + header Metronic) apa adanya untuk `scanner/index.blade.php`, karena di fase itu halamannya masih kosong. Di Fase 7, hasil scan harus tampil **besar dan kontras** (hijau "SERAHKAN N GELANG", merah "SUDAH DITUKAR", abu "QR TIDAK DIKENAL") dan dipakai petugas dari HP di lapangan — sidebar Metronic akan memakan ruang layar dan mengganggu fokus. Saat mengerjakan Fase 7, tinjau ulang apakah `scanner/index.blade.php` perlu tampilan fokus tanpa sidebar (mis. varian `class="app-blank"` seperti `layouts/auth.blade.php`, header tipis berisi nama petugas + tombol keluar saja) alih-alih tetap mewarisi `layouts/admin.blade.php` penuh.

## Alasan pemisahan

- Tidak ada `Repositories/` atau `Services/` untuk logika bisnis inti — `Actions/` sudah cukup tipis dan eksplisit untuk dua operasi paling kritis (registrasi dan redeem), tanpa lapisan abstraksi tambahan.
- `Support/` khusus helper murni tanpa state dan tanpa dependency Laravel selain fungsi bawaan PHP — mudah diuji sebagai unit test murni.
- `Services/Notifications/` menyimpan implementasi `TicketNotifier`; implementasi asli (Mail, HTTP ke VPS) masuk folder yang sama di Fase 10 tanpa mengubah controller/job. (Struktur awal menulis `Notifications/` di akar `app/`; diubah pada Fase 5 — lihat catatan fase itu.)
- Controller publik, scanner, dan admin dipisah namespace supaya middleware role jelas dan rute mudah digrup di `web.php`.
