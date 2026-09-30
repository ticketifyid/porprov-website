# Arsitektur

## Keputusan

| Topik | Keputusan | Status |
|---|---|---|
| Definisi kuota | 3.600 dihitung dari jumlah tiket (1 tiket = 1 gelang = 1 orang) | Disepakati |
| Tiket per pendaftaran | Maksimal 4 per transaksi | Disepakati |
| Model QR | 1 QR per registrasi, ditukar sekaligus dengan semua gelang | Disepakati |
| Input jumlah tiket | Stepper `− angka +` | Disepakati |
| Visibilitas kuota | Frontend hanya menerima `min(4, sisa)`; angka disebut saat user mentok | Disepakati |
| Validasi saat war | Hitung ulang di dalam `lockForUpdate()` saat submit | Disepakati |
| Format kode | `PJT26-7K3M9Q`, prefix event + 6 karakter Crockford Base32 | Disepakati |
| Metode scan | Kamera HP (dua langkah) dan alat scanner 2D mode keyboard (sekali scan) | Disepakati |
| Akun peserta | Tidak ada; akses via link bertoken | Rekomendasi, konfirmasi klien |
| Duplikat | 1 email = 1 pendaftaran per event (`email_canonical`) | Disepakati |
| Email | SMTP pihak ketiga, bukan mail server hosting | Rekomendasi |

## Aktor

- **Peserta**: mengisi form, menerima e-ticket, menunjukkan QR.
- **Sistem**: kunci kuota, buat kode + token, kirim notifikasi via queue + VPS WA.
- **Petugas scan** (role `scanner`): scan QR via HP atau alat scanner, serahkan gelang.
- **Admin** (role `admin`): jadwal & kuota, pencarian, kirim ulang notifikasi, akun petugas, export.

## Fase 1: Pendaftaran

1. `GET /` dan `GET /daftar`: jika `!$event->isOpen()` → halaman status (belum dibuka / ditutup). Jika `sisa <= 0` → status kuota penuh.
2. Form menerima `maxQty = max(0, min(4, sisa))`. Tidak ada angka sisa lain di HTML/JS.
3. `POST /daftar` → FormRequest. Email dirakit di `prepareForValidation()`: `email = lowercase(trim(email_local) + '@' + (email_domain === 'lainnya' ? email_domain_other : email_domain))`, lalu `email_canonical` dihitung dari `email` (aturan 13 di `CLAUDE.md`). Aturan: `email_local` wajib, tanpa `@` dan spasi; `email_domain` harus salah satu dari daftar di `docs/design/DESIGN.md` atau `lainnya`; jika `lainnya`, `email_domain_other` wajib dan berbentuk domain valid (minimal satu titik); `email` hasil rakitan divalidasi `email:rfc`. Error ditampilkan di field email. Lalu: `ticket_qty` 1–4, normalisasi HP (tanpa cek unique), unique `(event_id, email_canonical)`, Turnstile, throttle.
4. Transaksi:
   ```php
   $event = Event::lockForUpdate()->firstOrFail();
   abort_unless($event->isOpen(), 403);
   $sisa = $event->quota - $event->tickets_taken;
   if ($sisa <= 0) throw new QuotaException('Mohon maaf, kuota pendaftaran sudah penuh.');
   if ($qty > $sisa) throw new QuotaException("Sisa kuota tinggal {$sisa} tiket. Silakan kurangi jumlah tiket.");
   $event->increment('tickets_taken', $qty);
   Registration::create([... 'code' => generateCode($event->code_prefix), 'token' => Str::random(48) ...]);
   ```
5. `QuotaException` → `back()->withInput()->withErrors(['ticket_qty' => $msg])`. View menghitung ulang `maxQty`; stepper memakai `min(old('ticket_qty'), maxQty)`.
6. Setelah COMMIT: buat 2 baris `notification_logs` (email, whatsapp) status `pending`, dispatch job. Redirect `/daftar/sukses/{token}`.
7. Unique violation dari DB (race dua submit `email_canonical` sama) ditangkap sebagai `QueryException` → pesan ramah, bukan 500.

## Fase 2: Notifikasi

> Status: WA dan email asli DITUNDA, dikerjakan sendiri oleh pemilik proyek di akhir. Yang dibangun sekarang adalah titik sambungnya:
>
> ```php
> interface TicketNotifier {
>     /** @return string|null provider message id; lempar exception jika gagal */
>     public function sendEmail(Registration $registration): ?string;
>     public function sendWhatsApp(Registration $registration): ?string;
>     /** true hanya jika sendWhatsApp() benar-benar mengirim WA; menentukan teks kanal di halaman peserta */
>     public function deliversWhatsApp(): bool;
> }
> ```
>
> - `LogTicketNotifier` (default, `TICKET_NOTIFIER=log`): tulis ke log, kembalikan id `log-{uuid}`.
> - Binding di `AppServiceProvider` berdasarkan `config('services.ticket_notifier')`.
> - Job `SendTicketNotification(registrationId, channel)` memanggil interface, lalu mengurus `notification_logs`, retry, dan status. Job tidak tahu apa-apa soal SMTP atau VPS.
>
> Poin di bawah berlaku untuk job dan log; bagian SMTP/VPS menjadi tanggung jawab implementasi asli nanti.

- Cron tiap menit: `schedule:run` → `queue:work --stop-when-empty --max-time=50`.
- `SendTicketEmail`: SMTP pihak ketiga. `SendTicketWhatsApp`: POST ke VPS (`docs/whatsapp-api.md`); antrean dan jeda anti-banned diurus VPS.
- Sukses → `status=sent`, `sent_at`, `provider_message_id`. Gagal → `attempts++`, `last_error`; retry maks 3 lalu `failed`.
- Admin bisa kirim ulang per registrasi (reset ke `pending`, dispatch ulang).
- Isi pesan: nama, kode, jumlah tiket, link `/tiket/{token}`, tanggal & lokasi acara.

## Fase 3: Akses tiket

- `GET /tiket/{token}`: detail + QR SVG dari `token`, status penukaran. Email dan nomor ditampilkan tersamar.
- `GET/POST /cari-tiket`: input HP atau email → normalisasi → jika terdaftar, dispatch ulang notifikasi. Pencarian by HP bisa menemukan lebih dari satu registrasi (nomor HP tidak unik); kirim ulang notifikasi ke SEMUA registrasi yang cocok. Respons SELALU pesan netral yang sama, terlepas dari jumlah registrasi yang ditemukan (0, 1, atau banyak). Throttle ketat.

## Fase 4: Hari H (scan)

| | Kamera HP | Alat scanner |
|---|---|---|
| Input | html5-qrcode membaca kamera | Listener keyboard global: buffer karakter, jeda > 50 ms = reset, Enter = kirim, panjang harus 48 |
| Request | `POST /scan` `{token, method:"camera"}` → tampil detail | `POST /scan` `{token, method:"hardware"}` → langsung redeem |
| Konfirmasi | `POST /scan/{registration}/redeem` | Tidak ada |

- Token tidak ditemukan → log `not_found`, tampil "QR tidak dikenal" + pencarian manual.
- `redeemed_at` terisi → log `already_redeemed`, tampil "SUDAH DITUKAR" + jam + nama petugas.
- Redeem: `UPDATE registrations SET redeemed_at=now(), redeemed_by=? WHERE id=? AND redeemed_at IS NULL`. Affected 0 → perlakukan sebagai `already_redeemed`.
- Sukses → log `success`, layar besar "SERAHKAN N GELANG".
- Pencarian manual (kode/nama/HP) → dua langkah, `method=manual`. Normalisasi kode: uppercase, buang spasi, `O`→`0`, `I`/`L`→`1`.
- Halaman scanner wajib HTTPS, role `scanner`/`admin`, `is_active`. Listener keyboard mengabaikan event dari `input`/`textarea` (kotak pencarian manual). Flag `busy` mencegah kiriman ganda.

## Fase 5: Admin (Metronic)

Dashboard (kuota terpakai/sisa, pendaftar, check-in real-time sederhana), buka/tutup pendaftaran, pencarian & detail peserta, kirim ulang notifikasi, batalkan registrasi (kurangi `tickets_taken` dalam transaksi), manajemen akun petugas, export Excel/CSV (juga sebagai cadangan offline H-1).

## Cadangan hari H

Export daftar peserta (kode, nama, HP, jumlah tiket) H-1. Jika hosting down, petugas mencocokkan manual dan mencatat di kertas untuk diinput belakangan.
