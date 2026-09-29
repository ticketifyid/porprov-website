# Prompt per fase

Satu fase = satu sesi Claude Code. Di awal setiap fase masuk plan mode (Shift+Tab sampai mode plan), tempel prompt, cek rencananya, baru setujui. Setelah fase selesai: jalankan tes, commit, lalu `/clear` sebelum fase berikutnya.

---

## Fase 0: Orientasi (tanpa kode)

```
Baca CLAUDE.md dan semua file di docs/ (termasuk docs/design/DESIGN.md dan beberapa artboard di docs/design/artboards/).

Jangan menulis kode apa pun. Berikan:
1. Ringkasan pemahamanmu tentang sistem ini dalam 10 poin.
2. Daftar semua hal yang masih bertanda [ISI] atau belum jelas, yang akan menghalangi pengerjaan.
3. Hal yang menurutmu saling bertentangan antar dokumen.
4. Usulan struktur folder (controller, request, service, view, test) sebelum kita mulai.
```

## Fase 1: Fondasi data

```
Kerjakan fondasi data sesuai docs/erd.md, persis.

1. Migration untuk events, regencies, registrations, notification_logs, users (ganti migration users bawaan: username, role, is_active; tanpa email), scan_logs.
2. Model + relasi + casts. Event::isOpen() sesuai docs/erd.md.
3. Seeder: 1 event (Opening Ceremony Porprov Jateng XVII 2026, code_prefix PJT26, quota 3600, is_open false), 35 kab/kota Jawa Tengah + "Luar Jawa Tengah", 1 admin dari nilai .env (ADMIN_USERNAME, ADMIN_PASSWORD).
4. Helper PhoneNormalizer (aturan 5 di CLAUDE.md) dan RegistrationCodeGenerator (aturan 6).
5. Unit test untuk kedua helper: 0812.., +62812.., 62812.., "0812-3456 7890" → 628123456789..; kode cocok regex ^PJT26-[0-9A-HJKMNP-TV-Z]{6}$.

Selesai jika: migrate:fresh --seed berhasil dan semua tes lulus.
```

## Fase 2: Autentikasi admin & scanner + layout Metronic

```
Buat login untuk users (username + password), middleware role admin dan scanner (scanner boleh diakses admin juga), dan cek is_active.

Layout admin memakai Metronic dari public/metronic/ (asset jadi, tanpa build). Buat layout dasar + halaman dashboard kosong + halaman scanner kosong.

Jangan pakai Breeze/Jetstream/Fortify. Throttle login.
Tes: user nonaktif tidak bisa login; scanner tidak bisa membuka halaman admin.
```

## Fase 3: Fondasi desain halaman peserta

```
Baca docs/design/DESIGN.md dan semua artboard di docs/design/artboards/.

1. Buat public/css/app.css: token di :root persis dari DESIGN.md, reset minimal, komponen (strip Porprov, header mobile & desktop, tombol utama & sekunder, field, stepper, alert warn & info, badge, kartu, tiket, langkah bernomor). Mobile-first, breakpoint 992px (dan 768px untuk kasus di DESIGN.md).
2. Layout Blade publik (fonts Google Plus Jakarta Sans + Caveat) dan Blade component untuk tiap komponen di atas.
3. Satu halaman /_styleguide (hanya di APP_ENV=local) yang menampilkan semua komponen, supaya bisa dibandingkan dengan artboard.

Jangan pakai Bootstrap, Tailwind, atau build. Nilai visual harus sama dengan artboard; kalau ragu, kutip nilai dari file artboard-nya.
```

## Fase 4: Pendaftaran & kuota

```
Implementasi alur pendaftaran sesuai docs/arsitektur.md Fase 1 dan artboard Main, Form, Sukses, Status (versi mobile dan Desktop*).

TULIS FEATURE TEST DULU, pastikan gagal, baru implementasi:
- qty melebihi sisa → ditolak dengan pesan "Sisa kuota tinggal N tiket. Silakan kurangi jumlah tiket.", tickets_taken tidak berubah, input lama kembali
- sisa 0 → pesan kuota penuh
- nomor HP dengan 3 format berbeda dianggap sama → pendaftaran kedua ditolak
- ticket_qty 0 dan 5 ditolak
- HTML form tidak pernah memuat angka sisa kuota > 4 (uji dengan sisa 3600)
- event tertutup → halaman status
- sukses → tickets_taken bertambah, kode sesuai format, 2 notification_logs pending, job ter-dispatch (Queue::fake)

Stepper di frontend: vanilla JS, aturan persis di DESIGN.md bagian "Aturan stepper".
Turnstile: pakai site key dari .env; di environment testing, verifikasi di-bypass lewat config.
```

## Fase 5: Titik sambung notifikasi (tanpa WA/email asli)

```
Implementasi bagian "Status" di docs/arsitektur.md Fase 2. WA dan email asli TIDAK dikerjakan; itu tugas pemilik proyek nanti.

1. Interface App\Contracts\TicketNotifier persis seperti di docs/arsitektur.md.
2. LogTicketNotifier sebagai implementasi default, dipilih lewat config services.ticket_notifier (env TICKET_NOTIFIER=log), di-bind di AppServiceProvider.
3. Job SendTicketNotification(registrationId, channel): panggil interface, update notification_logs (sent + sent_at + provider_message_id, atau attempts++ + last_error), retry maks 3 lalu failed.
4. Pastikan pendaftaran (Fase 4) men-dispatch job ini untuk channel email dan whatsapp setelah COMMIT.
5. Schedule queue:work via cron sesuai docs/hosting.md.

Tes dengan fake implementation dari interface (bukan LogTicketNotifier): sukses, gagal lalu retry, gagal permanen → failed + last_error.
Jangan membuat Mailable, jangan memanggil HTTP ke VPS, jangan membaca docs/whatsapp-api.md.
Di akhir, tulis docs/notifikasi.md: cara menambah implementasi baru (kelas apa yang dibuat, method apa, cara mengganti binding, cara mengetesnya).
```

## Fase 6: Halaman tiket & cari tiket

```
Implementasi docs/arsitektur.md Fase 3 dan artboard Tiket, LupaTiket (mobile + Desktop*).

- /tiket/{token}: QR SVG dari token (tanyakan dulu package QR yang akan dipakai; harus bisa menghasilkan SVG tanpa Imagick), status belum/sudah ditukar, email dan nomor disamarkan.
- /cari-tiket: respons selalu pesan netral yang sama; dispatch ulang notifikasi hanya jika terdaftar; throttle.
Tes: token tidak valid → 404; respons cari tiket identik untuk data terdaftar dan tidak; halaman tiket tidak memuat email/nomor utuh.
```

## Fase 7: Scanner dua mode

```
Implementasi docs/arsitektur.md Fase 4 di layout Metronic.

- Halaman scanner dengan dua mode yang bisa dipilih: Kamera (html5-qrcode dari CDN yang diizinkan) dan Alat scanner (listener keyboard global persis seperti di arsitektur).
- POST /scan dan POST /scan/{registration}/redeem, pencarian manual.
- Tampilan hasil besar dan kontras: hijau "SERAHKAN N GELANG", merah "SUDAH DITUKAR (jam, petugas)", abu "QR TIDAK DIKENAL".
Tes: camera tidak mengubah status; hardware langsung redeem; redeem kedua → already_redeemed dan redeemed_at tidak berubah; semua percobaan tercatat di scan_logs dengan method yang benar; scanner nonaktif ditolak.
```

## Fase 8: Admin

```
Implementasi docs/arsitektur.md Fase 5 di Metronic:
dashboard (tiket terpakai/sisa, jumlah pendaftar, jumlah sudah check-in), pengaturan event (jadwal, is_open, quota), daftar & pencarian peserta, detail peserta + status notifikasi + tombol kirim ulang, batalkan registrasi (kurangi tickets_taken di transaksi yang sama), manajemen akun petugas, export CSV.
Tes: pembatalan mengembalikan kuota; kirim ulang me-reset log ke pending dan dispatch job; hanya admin yang bisa mengakses.
```

## Fase 9: Pengerasan & deploy

```
1. Review keamanan seluruh kode terhadap CLAUDE.md: cari pelanggaran aturan inti dan larangan, laporkan dulu sebelum memperbaiki.
2. Buat script sederhana (PHP atau bash + curl) untuk uji war kuota lokal: set sisa kuota 10, kirim 50 submit paralel, pastikan tickets_taken tidak pernah melebihi quota.
3. Tulis docs/deploy.md: langkah deploy ke shared hosting sesuai docs/hosting.md, isi .env produksi (tanpa nilai rahasia), cron, cek HTTPS, checklist H-1 (export cadangan, uji alat scanner, uji kamera di HP petugas, pastikan TICKET_NOTIFIER sudah bukan log).
```

## Fase 10: Notifikasi asli (dikerjakan pemilik proyek, opsional dibantu Claude Code)

```
Baca docs/notifikasi.md dan docs/whatsapp-api.md (sudah diisi).
Buat implementasi TicketNotifier untuk produksi: email lewat SMTP pihak ketiga (Mailable + view, isi sesuai template di docs/whatsapp-api.md) dan WhatsApp lewat HTTP ke VPS sesuai kontrak, timeout 10 detik.
Ganti binding lewat TICKET_NOTIFIER. Jangan mengubah job, controller, atau tes yang sudah ada selain menambah tes untuk implementasi baru (Mail::fake, Http::fake).
```
