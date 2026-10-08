# Kontrak API WhatsApp (WAHA, VPS)

Diuji manual 8 Oktober 2026. Ini kontrak final — jangan dikarang ulang; bagian yang ditandai "BELUM DIVERIFIKASI" memang belum pernah diuji dengan kegagalan nyata dan boleh disesuaikan nanti.

## Server

- **WAHA** (WhatsApp HTTP API, versi gratis, engine **NOWEB**), **1 sesi**, berjalan di VPS pemilik proyek.
- Base URL: env `WAHA_URL` (produksi: `https://wa.dwiprakoso.my.id`).
- Autentikasi: header `X-Api-Key: <WAHA_API_KEY>`.
- Nama sesi: env `WAHA_SESSION`, nilainya **`dwilaptop`** (BUKAN `"default"`).
- Timeout 10 detik per panggilan (`App\Services\Notifications\WahaClient`).

## chatId

Nomor internasional tanpa `+` dan tanpa `0` di depan, diikuti `@c.us`. Contoh: `6281234567890@c.us`. Diambil dari `App\Support\PhoneNormalizer::normalize()` (nomor di `registrations.phone` sudah dalam bentuk ini, tinggal ditambah `@c.us`).

## Endpoint

Semua `POST` berbody JSON dan **sukses dengan HTTP 201**.

| Method | Path | Body | Keterangan |
|---|---|---|---|
| GET | `/api/sessions` | — | Mengembalikan array sesi. Cek kesehatan: entri dengan `name == WAHA_SESSION` harus berstatus `"WORKING"`. Jika bukan, jangan kirim apa pun. |
| POST | `/api/startTyping` | `{session, chatId}` | |
| POST | `/api/sendText` | `{session, chatId, text}` | |
| POST | `/api/stopTyping` | `{session, chatId}` | Kegagalan di sini tidak membatalkan pengiriman (pesan sudah terkirim lewat `sendText`). |

## BELUM DIVERIFIKASI

Bagian ini sengaja tidak dikarang detail — akan disesuaikan setelah ada kegagalan nyata (lihat `App\Services\Notifications\WahaFailureClassifier`):

- **Bentuk body respons `sendText` yang sukses.** Id pesan diambil defensif: string `id`, atau `id._serialized`, atau `key.id`; jika tidak ada satupun, `provider_message_id` disimpan `null`.
- **Endpoint cek nomor terdaftar** (belum dipakai).
- **Bentuk error saat akun WhatsApp dibatasi** (kode umum: `463`). Yang diketahui: body responsnya memuat teks `"463"`. Status HTTP persisnya belum pasti.

Pesan error mentah (sudah disaring dari kredensial/PII oleh `App\Support\NotificationErrorSanitizer`, maks 500 karakter) disimpan ke `notification_logs.last_error` supaya klasifikasi ini bisa diperbaiki begitu ada kegagalan sungguhan.

## Kebijakan pengiriman

Nomor WhatsApp pengirim **tidak resmi** dan bisa dibanned. Kebijakan ini ditegakkan oleh kode (`App\Console\Commands\WaSendNext`), bukan sekadar catatan:

- **Satu pesan per eksekusi command**, tidak pernah paralel, tidak pernah di dalam request/transaksi.
- **Jeda acak** antar pesan: `WA_MIN_GAP_SECONDS` (45) sampai `WA_MAX_GAP_SECONDS` (90) detik, disimpan sebagai `next_allowed_at` di tabel `wa_throttle_states`.
- **Simulasi mengetik**: `startTyping` → tunggu sebanding panjang pesan (minimal 2 detik, maksimal `WA_TYPING_MAX_SECONDS` = 8, plus sedikit acak) → `sendText` → `stopTyping`.
- **Batas harian** `WA_DAILY_CAP` (100), dihitung dari `notification_logs` kanal `whatsapp` berstatus `sent` hari ini (timezone aplikasi).
- **Jam kirim** `WA_SEND_FROM` (08:00) sampai `WA_SEND_UNTIL` (20:00), timezone aplikasi.
- **Hanya untuk pendaftar yang menyetujui** (`registrations.wa_consent = true`).
- **Circuit breaker**: pause `WA_PAUSE_HOURS` (6 jam) jika sesi bukan `WORKING`, koneksi/timeout gagal, HTTP 5xx **tiga kali berturut-turut** (`WA_MAX_CONSECUTIVE_FAILURES` = 3), atau body respons memuat `"463"`. Kegagalan lain (mis. nomor tidak valid) hanya menaikkan `attempts` baris itu, tidak memicu breaker, dan mereset hitungan beruntun. Lihat `docs/notifikasi.md` "Outbox WhatsApp" untuk alur lengkapnya.

## Template pesan

```
Halo {nama},
Pendaftaran Opening Ceremony Porprov Jateng XVII 2026 berhasil.

Kode registrasi: {kode}
Jumlah tiket: {qty} (tukar dengan {qty} gelang)
Acara: {tanggal & jam}, {lokasi}

E-ticket dan QR: {link /tiket/{token} dari APP_URL}

Tunjukkan QR saat registrasi ulang.
Pesan ini dikirim otomatis karena Anda mendaftar dan menyetujui notifikasi WhatsApp.
```

Baris "Acara" disembunyikan jika tanggal maupun lokasi acara masih kosong (sama seperti email).
