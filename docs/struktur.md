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

```
app/
  Actions/
    RegisterAttendee.php              # transaksi kuota: lockForUpdate, hitung sisa, increment, create registration
    RedeemRegistration.php            # update bersyarat WHERE id=? AND redeemed_at IS NULL, catat scan_logs
  Support/
    PhoneNormalizer.php               # aturan 5 CLAUDE.md
    RegistrationCodeGenerator.php     # aturan 6 CLAUDE.md
    EmailCanonicalizer.php            # aturan 13 CLAUDE.md
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
