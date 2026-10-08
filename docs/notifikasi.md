# Notifikasi e-ticket

Dokumen ini untuk orang yang mengurus pengiriman asli (Fase 10 di `docs/prompts.md`): email lewat SMTP dan WhatsApp lewat WAHA di VPS. **Email sudah diimplementasikan** (`TICKET_NOTIFIER=mail`, lihat di bawah). **WhatsApp juga sudah diimplementasikan** (`TICKET_WA_DRIVER=waha`, lihat "Outbox WhatsApp" di bawah) tapi dikirim lewat command terjadwal, BUKAN lewat job — alurnya beda dari email.

## Alur

```
POST /daftar
  └─ RegisterAttendee (DB::transaction, lockForUpdate, insert registrations)
     ── COMMIT ──
        └─ RegistrationController::queueNotifications()
             ├─ email:    notification_logs 'pending' + SendTicketNotification::dispatch(...)
             └─ whatsapp: notification_logs 'pending' (jika wa_consent) atau 'skipped' (jika tidak)
                          — TIDAK dispatch job, lihat "Outbox WhatsApp" di bawah

cron tiap menit → schedule:run → queue:work --stop-when-empty --max-time=50
  └─ SendTicketNotification::handle(TicketNotifier $notifier)  [kanal email saja]
       ├─ sukses → notification_logs: status 'sent', sent_at, provider_message_id
       ├─ gagal  → attempts++, last_error, status tetap 'pending', exception dilempar ulang
       └─ gagal di percobaan ke-3 → failed(): status 'failed', last_error
```

Notifikasi **tidak pernah** dikirim di dalam transaksi atau sinkron di dalam request (aturan 8 `CLAUDE.md`). Kanal WhatsApp sengaja menyimpang dari alur job/queue di atas sejak Fase 10 — lihat "Outbox WhatsApp".

## Kontrak `App\Contracts\TicketNotifier`

```php
interface TicketNotifier
{
    /** @return string|null provider message id; lempar exception jika gagal */
    public function sendEmail(Registration $registration): ?string;

    /** @return string|null provider message id; lempar exception jika gagal */
    public function sendWhatsApp(Registration $registration): ?string;

    /** true hanya jika sendWhatsApp() benar-benar mengirim pesan WhatsApp */
    public function deliversWhatsApp(): bool;
}
```

`deliversWhatsApp()` menentukan teks kanal di halaman peserta (beranda, form, halaman sukses, cari tiket) lewat view composer di `AppServiceProvider::boot()`: `false` → "email" saja, `true` → "email dan WhatsApp". `LogTicketNotifier` dan `MailTicketNotifier` sekarang mengembalikan `false`. **Begitu WhatsApp asli dipasang, implementasinya wajib mengembalikan `true`** — tanpa itu peserta tidak diberi tahu bahwa e-ticket juga dikirim lewat WhatsApp. Template email tidak menyebut WhatsApp sama sekali, jadi tidak ikut berubah.

Dua aturan yang tidak boleh dilanggar implementasi apa pun:

1. **Berhasil = kembalikan nilai.** Boleh `null` kalau provider tidak memberi id pesan; `null` tetap dihitung berhasil dan log ditandai `sent`.
2. **Gagal = lempar exception.** Jangan mengembalikan `false`/`null` untuk menandai gagal, dan jangan menelan exception sendiri. Job memakai exception itu untuk menaikkan `attempts`, mengisi `last_error`, dan meminta queue melakukan retry. Kalau exception ditelan, kegagalan akan tercatat sebagai `sent`.

Data yang tersedia di `$registration`: `name`, `code`, `token`, `email`, `phone` (sudah `62xxx`), `ticket_qty`, dan relasi `event` (nama, `event_starts_at`, `venue`) serta `regency`. Link tiket = `url('/tiket/'.$registration->token)` — pakai `token`, jangan `code` atau `id` (aturan 7).

Catatan: kirim e-ticket ke kolom `email`, **bukan** `email_canonical`. `email_canonical` hanya untuk cek duplikat (aturan 13).

## Implementasi yang ada sekarang

| Nilai `TICKET_NOTIFIER` | Kelas | Perilaku |
|---|---|---|
| `log` (default) | `App\Services\Notifications\LogTicketNotifier` | Tidak mengirim apa pun. Menulis satu baris `Log::info` per notifikasi dan mengembalikan id `log-{uuid}`. |
| `mail` (produksi) | `App\Services\Notifications\MailTicketNotifier` | **Email:** Mailable `App\Mail\TicketMail` dikirim langsung (`Mail::to(...)->send(...)`, bukan queue, karena sudah berjalan di dalam job) ke kolom `email`, lewat mailer default (`MAIL_MAILER`). Mengembalikan Message-ID sebagai `provider_message_id`. Exception transport tidak ditangkap, jadi job mencatat gagal dan queue melakukan retry. **WhatsApp:** tergantung `TICKET_WA_DRIVER` — `waha` kirim sungguhan lewat WAHA (lihat "Outbox WhatsApp" di bawah), selain itu (default `log`) diteruskan ke `LogTicketNotifier`. |

Nilai lain sengaja **melempar `InvalidArgumentException`** saat resolve, bukan diam-diam jatuh ke `log`. Salah ketik di produksi harus langsung terlihat, bukan berakhir "semua tercatat sent tapi tidak ada yang menerima".

### Email e-ticket (`TicketMail`)

- Subjek: `E-ticket Opening Ceremony Porprov Jateng XVII 2026 - {kode}`.
- View HTML `resources/views/mail/ticket.blade.php` (CSS inline, navy `#0E2A6B` dan token lain dari `docs/design/DESIGN.md`, tanpa gambar eksternal) dan teks polos `resources/views/mail/ticket-text.blade.php`.
- Isi: sapaan nama, kode registrasi, "{N} tiket, tukar dengan {N} gelang", tanggal & lokasi acara (masing-masing disembunyikan jika `event_starts_at`/`venue` null), tombol + link `/tiket/{token}`, dan petunjuk menunjukkan QR saat registrasi ulang.
- Link dibuat dengan `route('tiket.show', $token)`. Di worker tidak ada request, jadi host-nya diambil dari `APP_URL` — nilai itu harus `https://DOMAIN` produksi.

### Konfigurasi mail

| Variabel | Keterangan |
|---|---|
| `MAIL_MAILER` | `smtp` di produksi (`log` di lokal). |
| `MAIL_HOST` / `MAIL_PORT` | Dari penyedia mailbox. |
| `MAIL_SCHEME` | Laravel versi ini memakai `MAIL_SCHEME`, **bukan** `MAIL_ENCRYPTION`. `smtps` untuk port 465, `smtp` untuk 587 (STARTTLS otomatis). Kosong/`null` = ditebak dari port. |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | Kredensial mailbox, hanya di `.env`. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | Alamat di domain yang SPF/DKIM-nya sudah benar (lihat `docs/deploy.md`). |
| `MAIL_TIMEOUT` | Default 10 detik (`config/mail.php`). Supaya server SMTP yang menggantung tidak menghabiskan jatah 50 detik worker. |

## Outbox WhatsApp

Nomor WhatsApp pengirim **tidak resmi** dan berisiko dibanned, jadi pengirimannya diatur jauh lebih ketat daripada email: satu pesan per eksekusi command, jeda acak, jam kirim, batas harian, dan circuit breaker. Kontrak API dan kebijakan lengkap ada di `docs/whatsapp-api.md`.

### Kenapa bukan job (menyimpang dari Fase 5)

Email tetap lewat `SendTicketNotification` + queue seperti Fase 5. WhatsApp **tidak** — `RegistrationController::queueNotifications()` dan `App\Actions\ResendTicketNotifications` hanya mencatat baris `notification_logs` kanal `whatsapp` (`pending` jika `registrations.wa_consent = true`, `skipped` jika tidak) tanpa pernah men-dispatch job. Alasannya: queue Laravel tidak punya tempat alami untuk memaksakan "satu pesan per menit dengan jeda acak lintas SEMUA registrasi" — setiap job berjalan independen. Sebagai gantinya, command `wa:send-next` mengambil **satu** baris pending tertua setiap kali dijalankan, dan jeda terjadi secara alami karena command ini dijadwalkan `everyMinute()` tapi menolak mengirim sebelum `next_allowed_at`.

### Alur `wa:send-next`

```
cron tiap menit → schedule:run → wa:send-next
  1. keluar jika: TICKET_WA_DRIVER != waha, sedang pause, di luar jam kirim,
     batas harian tercapai, atau now() < next_allowed_at
  2. baris 'sending' yang menggantung > 10 menit dikembalikan ke 'pending'
  3. GET /api/sessions — sesi harus WORKING, kalau tidak: pause + keluar
  4. DB::transaction + lockForUpdate: klaim SATU baris pending tertua
     (channel=whatsapp, wa_consent=true, registrasi belum dibatalkan),
     tandai 'sending', commit
  5. kirim lewat TicketNotifier::sendWhatsApp() DI LUAR transaksi
       ├─ sukses → 'sent' + sent_at + provider_message_id,
       │           next_allowed_at = now() + acak(45..90 detik), consecutive_failures = 0
       └─ gagal  → attempts++, last_error (tersaring), lalu:
                     attempts >= 3  → 'failed'
                     attempts < 3   → kembali ke 'pending' (dicoba run berikutnya)
                   klasifikasi kegagalan (WahaFailureClassifier) menentukan breaker:
                     IMMEDIATE (koneksi/timeout, body memuat "463") → pause langsung
                     COUNTS (HTTP 5xx)   → consecutive_failures++, pause jika >= WA_MAX_CONSECUTIVE_FAILURES
                     NONE (mis. nomor tidak valid) → consecutive_failures direset ke 0
```

State pacing/breaker (`next_allowed_at`, `paused_until`, `pause_reason`, `consecutive_failures`) disimpan di tabel `wa_throttle_states` (baris tunggal, `App\Models\WaThrottleState::current()`), **bukan** cache — cache bisa dihapus kapan saja oleh `cache:clear` dan akan membuang state pacing yang sedang berjalan.

Dua kelas pendukung di `app/Services/Notifications/`:

- `WahaClient`: transport HTTP murni (`sessions()`, `startTyping()`, `sendText()`, `stopTyping()`), tanpa logika kebijakan. API key tidak pernah masuk log/exception.
- `WahaFailureClassifier`: mengklasifikasi kegagalan `sendText()` (lewat `App\Exceptions\WahaSendFailedException` yang membawa `Response` asli) menjadi `IMMEDIATE` / `COUNTS` / `NONE`. Tes sendiri di `tests/Unit/WahaFailureClassifierTest.php` — kelas ini yang disesuaikan lebih dulu kalau klasifikasinya ternyata salah setelah ada kegagalan nyata.

`App\Services\Notifications\NotificationLogRecorder` dipakai ulang oleh `SendTicketNotification` (email) dan `wa:send-next` (whatsapp) untuk "tandai sent" dan "attempts++ + last_error", supaya logikanya tidak diduplikasi.

### Command

| Command | Fungsi |
|---|---|
| `wa:send-next` | Dijadwalkan `everyMinute()->withoutOverlapping()` di `routes/console.php`. Kirim (paling banyak) satu pesan, lalu keluar. |
| `wa:status` | Ringkasan: terkirim hari ini vs `WA_DAILY_CAP`, jumlah pending/sending/failed/skipped, status pause, `next_allowed_at`, `consecutive_failures`. |
| `wa:pause {hours=6} {--reason=}` | Jeda paksa (mis. sebelum maintenance VPS). |
| `wa:resume` | Lepas pause dan reset `consecutive_failures` ke 0. |

### Menaikkan `WA_DAILY_CAP` bertahap

Nomor WhatsApp baru/tidak resmi lebih mudah dibatasi WhatsApp kalau langsung mengirim dalam volume besar. Disarankan menaikkan `WA_DAILY_CAP` bertahap selama beberapa hari pertama produksi (mis. 20 → 50 → 100) sambil memantau `wa:status` dan `notification_logs` kanal `whatsapp` berstatus `failed`. Turunkan lagi atau `wa:pause` kalau mulai banyak kegagalan `IMMEDIATE`/`COUNTS`.

### Persetujuan (`wa_consent`)

Checkbox di form pendaftaran ("Kirimi saya tiket dan informasi acara lewat WhatsApp"), **tidak tercentang secara default**, hanya tampil kalau `TicketNotifier::deliversWhatsApp()` bernilai `true` (yaitu `TICKET_WA_DRIVER=waha`). Disimpan di `registrations.wa_consent` (boolean, default `false`). Pendaftar yang tidak mencentang: baris `notification_logs` whatsapp langsung `skipped`, tidak pernah diambil `wa:send-next`.

## Menambah implementasi baru

Untuk WhatsApp: cukup ganti delegasi `sendWhatsApp()` di `MailTicketNotifier` (atau buat kelas gabungan seperti di bawah) dan ubah `deliversWhatsApp()` menjadi `true`; binding `mail` tidak perlu diubah namanya kalau Anda tidak mau.

1. Buat kelas di `app/Services/Notifications/`, mis. `SmtpTicketNotifier.php`, `implements App\Contracts\TicketNotifier`. Implementasi asli Fase 10 (SMTP maupun WhatsApp) ditaruh di folder yang sama, **bukan** di `app/Notifications/` — folder itu tidak dipakai proyek ini supaya tidak tertukar dengan Notification bawaan Laravel.
2. Tambah satu arm di `match` pada `AppServiceProvider::register()`:

   ```php
   return match ($driver) {
       'log' => new LogTicketNotifier,
       'smtp' => new SmtpTicketNotifier,
       default => throw new InvalidArgumentException(...),
   };
   ```

   Kalau email dan WhatsApp ditangani dua kelas berbeda, buat satu kelas gabungan yang mendelegasikan keduanya (mis. `ProductionTicketNotifier` yang di constructor-nya menerima kedua kelas itu) — interface-nya satu, jadi binding-nya juga satu.
3. Isi `TICKET_NOTIFIER=smtp` di `.env` produksi (dan tambahkan ke `.env.example` tanpa nilai rahasia).
4. Kredensial SMTP / URL & token VPS hanya lewat `.env` + `config/services.php`. Jangan hardcode.

### Yang tidak boleh diubah

- Constructor `SendTicketNotification(int $registrationId, string $channel)` — `RegistrationController` dan tes yang ada bergantung padanya.
- `RegisterAttendee` dan alur kuota. Implementasi baru hanya menambah kelas + arm binding + tesnya sendiri.
- Jangan memindahkan pengiriman ke dalam request atau ke dalam transaksi.
- `RegistrationController::queueNotifications()` sudah sengaja diubah Fase 10 (kanal whatsapp tidak lagi dispatch job) — lihat "Outbox WhatsApp" di atas. Jangan mengembalikannya ke dispatch job tanpa membaca alasannya dulu.

## Perilaku retry

| Hal | Nilai |
|---|---|
| Maks percobaan | `SendTicketNotification::$tries = 3` |
| Jeda antar percobaan | `$backoff = [60, 300]` detik (percobaan ke-2 ~1 menit, ke-3 ~5 menit setelah gagal) |
| Worker | cron per menit → `schedule:run` → `queue:work --stop-when-empty --max-time=50` (`routes/console.php`, `docs/hosting.md`) |

Karena worker hanya hidup sebentar per menit, implementasi baru **wajib memasang timeout** pada koneksi keluar (mis. 10 detik untuk HTTP ke VPS) supaya satu tujuan yang menggantung tidak menghabiskan jatah 50 detik milik seluruh antrean.

Kolom `notification_logs` yang diurus job:

| Kolom | Diisi saat |
|---|---|
| `status` | `pending` (awal) → `sent` (berhasil) / `failed` (setelah percobaan ke-3) |
| `attempts` | naik 1 setiap kali pengiriman melempar exception |
| `last_error` | nama kelas exception + pesan yang **sudah disaring**, maksimal 500 karakter; dikosongkan lagi kalau retry berhasil. Lihat "Penyaringan `last_error`" di bawah. |
| `sent_at` | waktu berhasil |
| `provider_message_id` | nilai balik dari notifier |

### Penyaringan `last_error`

Exception SMTP/HTTP bisa memuat kredensial, alamat email, nomor HP, atau token tiket. `SendTicketNotification::errorMessage()` (dipakai `handle()` dan `failed()`) menyaring pesan sebelum disimpan:

| Pola | Diganti menjadi |
|---|---|
| kredensial di URL (`smtp://user:pass@host`) | `smtp://[disaring]@host` |
| `Authorization: ...`, `Bearer ...`, `Basic ...` | `[disaring]` |
| `password=`, `token:`, `api_key=`, `secret=`, `user=`, `username "..."`, dst. | `password=[disaring]`, `username "[disaring]"` |
| alamat email | `[email]` |
| nomor telepon (≥ 9 digit) | `[nomor]` |
| string alfanumerik ≥ 32 karakter (token tiket, API key) | `[token]` |

Host, port, dan kode balasan SMTP (mis. `535 5.7.8`) tetap ada supaya penyebab kegagalan masih bisa dibaca. Implementasi baru tidak perlu menyaring sendiri, tapi sebaiknya tidak sengaja memasukkan data peserta ke pesan exception.

Perilaku job yang perlu diketahui:

- Log berstatus `sent` tidak akan dikirim ulang (job idempoten; worker bisa mati setelah kirim tapi sebelum job dihapus dari antrean).
- Registrasi yang sudah dihapus → job selesai diam-diam, tidak dihitung gagal.
- Kanal di luar `email`/`whatsapp` → `InvalidArgumentException` sebelum menyentuh database.

Kirim ulang manual dari halaman admin (reset log ke `pending` + dispatch ulang) baru dibuat pada Fase 8.

## Mengetes implementasi baru

Pola yang dipakai `tests/Feature/NotificationJobTest.php`: tes job memakai **implementasi palsu dari interface**, bukan `LogTicketNotifier`, lalu di-bind dengan

```php
$this->app->instance(TicketNotifier::class, $fake);
```

Tes untuk implementasi asli berdiri sendiri dan tidak menyentuh job:

- SMTP: `Mail::fake()`, assert Mailable terkirim ke `$registration->email` dengan kode dan link tiket yang benar. Sudah ada di `tests/Feature/MailTicketNotifierTest.php` (termasuk tanggal/lokasi null, WA tetap log, dan penyaringan `last_error`).
- WhatsApp: `Http::fake()` + `Sleep::fake()` (supaya simulasi mengetik tidak benar-benar menunggu), assert urutan `startTyping`/`sendText`/`stopTyping` dan **exception dilempar** saat respons bukan 201 atau timeout. Sudah ada di `tests/Feature/Notifications/WahaClientTest.php` dan `tests/Feature/Notifications/WaSendNextCommandTest.php` (pacing, jam kirim, batas harian, circuit breaker).

## Memeriksa di produksi

```sql
SELECT channel, status, COUNT(*) FROM notification_logs GROUP BY channel, status;
SELECT r.code, n.channel, n.attempts, n.last_error
FROM notification_logs n JOIN registrations r ON r.id = n.registration_id
WHERE n.status = 'failed' ORDER BY n.updated_at DESC;
```

Antrean menumpuk (`SELECT COUNT(*) FROM jobs`) biasanya berarti cron tidak jalan atau worker mati sebelum selesai. Job yang menyerah tercatat juga di tabel `failed_jobs` (`php artisan queue:failed`, ulangi dengan `php artisan queue:retry`).

Sebelum go-live, pastikan `TICKET_NOTIFIER=mail` di `.env` produksi, bukan `log` (masuk checklist H-1 di `docs/deploy.md`). Periksa juga SPF/DKIM/DMARC domain pengirim dan batas kirim harian mailbox (`docs/deploy.md`, "Email pengirim").

WhatsApp: `php artisan wa:status` lewat SSH untuk ringkasan cepat. `TICKET_WA_DRIVER=waha` hanya diaktifkan setelah uji manual di VPS (`docs/deploy.md`).
