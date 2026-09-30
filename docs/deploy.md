# Deploy ke shared hosting

Panduan rilis untuk Ticketify — Registrasi Opening Ceremony Porprov Jateng XVII 2026.
Dibuat pada Fase 9 (`docs/prompts.md`). Baca bersama `docs/hosting.md` (batasan server)
dan `docs/notifikasi.md` (implementasi pengiriman e-ticket).

> **Nilai bertanda [ISI] di `docs/hosting.md` harus sudah terisi sebelum deploy.**
> Panduan ini tidak menebak nama penyedia, path, atau kredensial apa pun.

---

## 0. Ringkasan urutan

| # | Langkah | Sekali saja / tiap rilis |
|---|---|---|
| 1 | Siapkan database & akun MySQL | sekali |
| 2 | Arahkan document root ke `public/` | sekali |
| 3 | `git clone` + `composer install --no-dev` | sekali (lalu `git pull` tiap rilis) |
| 4 | Isi `.env` produksi | sekali (tinjau tiap rilis) |
| 5 | `key:generate`, `migrate --force`, `db:seed` | sekali |
| 6 | Cache konfigurasi & rute | tiap rilis |
| 7 | Pasang cron `schedule:run` | sekali |
| 8 | Cek HTTPS, proxy, dan IP asli | sekali (verifikasi ulang tiap rilis) |
| 9 | Checklist H-1 | sekali, menjelang hari H |

---

## 1. Database

Buat database dan user MySQL/MariaDB (InnoDB) lewat panel hosting. Catat nama database,
user, dan password — hanya masuk ke `.env`, tidak pernah ke repo.

Engine harus InnoDB: seluruh pengamanan kuota bergantung pada transaksi + row lock
(`Event::lockForUpdate()`, aturan 1 `CLAUDE.md`). MyISAM tidak punya keduanya dan akan
membuat `tickets_taken` bisa melewati `quota` saat banyak pendaftar bersamaan.

## 2. Document root

Arahkan document root domain ke folder `public/` di dalam aplikasi, bukan ke root repo.
Kalau panel hosting tidak mengizinkan (document root terkunci di `public_html/`):

- taruh aplikasi di luar `public_html/` (mis. `~/ticketify/`), lalu
- isi `public_html/` dengan isi `public/` dan sesuaikan dua path di `index.php`.

Jangan menyalin seluruh repo ke `public_html/`. Kalau `.env`, `storage/`, atau
`app/` bisa diakses lewat URL, kredensial database dan log tiket ikut terbuka.

**Verifikasi:** `https://DOMAIN/.env` dan `https://DOMAIN/storage/logs/laravel.log`
harus 404, dan `https://DOMAIN/up` harus membalas halaman health check Laravel.

## 3. Kode & dependensi

```bash
git clone <repo> ~/ticketify
cd ~/ticketify
composer install --no-dev --optimize-autoloader
```

- `vendor/` dan `.env` tidak di-commit (lihat `.gitignore`).
- Tidak ada `npm`, `vite`, atau build apa pun — CSS/JS sudah jadi di `public/`
  (`CLAUDE.md` bagian "Dilarang").
- Kalau Composer tidak tersedia di server (`docs/hosting.md`), jalankan
  `composer install --no-dev --optimize-autoloader` di lokal lalu unggah `vendor/`
  apa adanya. Versi PHP lokal harus sama mayor-minornya dengan server.

Izin tulis yang dibutuhkan:

```bash
chmod -R 775 storage bootstrap/cache
```

`php artisan storage:link` **tidak boleh dijalankan** (`CLAUDE.md`; fungsi `symlink()`
biasanya dimatikan di shared hosting). Proyek ini tidak menyimpan file upload publik,
jadi symlink itu memang tidak dibutuhkan. Kalau suatu saat perlu, buat manual lewat SSH:
`ln -s ../storage/app/public public/storage`.

## 4. `.env` produksi

Salin `.env.example` menjadi `.env`, lalu isi. **Checklist wajib** — deploy dianggap
belum selesai kalau ada satu baris yang tidak sesuai:

| Kunci | Nilai produksi | Kenapa |
|---|---|---|
| `APP_ENV` | `production` | Mematikan `/_styleguide`, mengaktifkan `DB::prohibitDestructiveCommands` (migrate:fresh/db:wipe ditolak), dan mematikan `trustProxies(at: '*')` yang hanya untuk `local`. |
| `APP_DEBUG` | `false` | `true` menampilkan stack trace berisi query, path, dan isi `.env` ke siapa pun yang memicu error. |
| `APP_URL` | `https://DOMAIN` | Dipakai untuk membangun link e-ticket di notifikasi. Harus `https://`, bukan `http://`, dan tanpa garis miring di akhir. |
| `APP_TIMEZONE` | `Asia/Jakarta` | Jadwal acara, jam penukaran gelang, dan badge "Sudah ditukar" dibaca/ditulis dalam WIB (`docs/struktur.md` Fase 6). Salah zona = jam di tiket meleset 7 jam. |
| `APP_KEY` | hasil `php artisan key:generate` | Kunci enkripsi sesi & cookie. Jangan pakai kunci yang sama dengan lokal. |
| `SESSION_SECURE_COOKIE` | `true` | Cookie sesi petugas hanya dikirim lewat HTTPS. Tanpa ini, satu request `http://` yang lolos bisa membocorkan sesi admin di Wi-Fi venue. |
| `TICKET_NOTIFIER` | **`mail`** | Email e-ticket asli (WhatsApp masih log sampai diimplementasikan). `log` hanya menulis ke `storage/logs` dan tidak mengirim apa pun: peserta tidak akan menerima e-ticket. Lihat `docs/notifikasi.md`. |
| `DB_*` | kredensial produksi | — |
| `QUEUE_CONNECTION` | `database` | Notifikasi dikirim lewat antrean, dijalankan cron (langkah 7). |
| `SESSION_DRIVER` | `database` | Shared hosting bisa membersihkan `storage/framework/sessions` kapan saja. |
| `CACHE_STORE` | `database` | Dipakai throttle per IP dan rate limit `/cari-tiket`. |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | akun admin pertama | `AdminUserSeeder` **menolak** kata sandi `password` atau yang kurang dari 12 karakter saat `APP_ENV=production`. |
| `TURNSTILE_ENABLED` | `true` | Proteksi bot pada form pendaftaran publik. |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY` | dari dashboard Cloudflare | Secret key hanya di `.env`. |
| `LOG_LEVEL` | `warning` (atau `error`) | `debug` di produksi menumpuk log besar di kuota disk shared hosting. |
| `MAIL_MAILER` | `smtp` | `log` di produksi = email tidak pernah keluar meski `TICKET_NOTIFIER=mail`. |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_SCHEME` | dari penyedia mailbox | `MAIL_SCHEME=smtps` untuk port 465, `smtp` untuk 587. Laravel versi ini tidak membaca `MAIL_ENCRYPTION`. |
| `MAIL_USERNAME` / `MAIL_PASSWORD` | kredensial mailbox | Hanya di `.env`. |
| `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` | alamat di domain sendiri | Domainnya harus lolos SPF/DKIM/DMARC (lihat "Email pengirim" di bawah), kalau tidak e-ticket masuk spam. |
| `MAIL_TIMEOUT` | `10` | Detik. Server SMTP yang menggantung tidak boleh menghabiskan jatah 50 detik worker. |

Cek cepat setelah mengisi:

```bash
php artisan about --only=environment
```

Pastikan `Environment = production`, `Debug Mode = OFF`, `Timezone = Asia/Jakarta`.

### Email pengirim

Dicek sebelum pendaftaran dibuka, bukan pada hari H:

1. **SPF**: record TXT domain pengirim memuat server/penyedia SMTP yang dipakai
   (`dig TXT DOMAIN` atau cek di panel DNS). Hanya boleh ada **satu** record `v=spf1`.
2. **DKIM**: aktifkan di panel hosting/penyedia SMTP, lalu pasang record TXT
   `selector._domainkey.DOMAIN` yang diberikan.
3. **DMARC**: record TXT `_dmarc.DOMAIN`, minimal `v=DMARC1; p=none; rua=mailto:...`
   untuk awal.
4. Kirim satu email uji ke <https://www.mail-tester.com> (daftarkan peserta uji dengan
   alamat dari situs itu) dan pastikan SPF, DKIM, DMARC lolos. Cek juga email uji yang
   masuk ke Gmail: buka "Tampilkan yang asli" → ketiganya harus `PASS`.
5. **Batas kirim mailbox**: tanyakan ke penyedia batas kirim per jam dan per hari.
   Kuota 3.600 tiket bisa berarti sampai 3.600 pendaftaran (1 tiket per pendaftaran),
   ditambah kirim ulang dari `/cari-tiket` dan admin. Shared hosting sering membatasi
   100–500 email/jam; kalau batasnya lebih kecil dari lonjakan pendaftar di jam pertama,
   email di atas batas akan gagal dan dicoba ulang (maks. 3 kali, ~6 menit total) lalu
   berstatus `failed`. Pakai layanan SMTP pihak ketiga yang batasnya cukup, atau siapkan
   kirim ulang manual dari admin.

## 5. Migrasi & akun admin

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

- `--force` wajib karena produksi tidak interaktif.
- Seeder mengisi 1 event (`PJT26`, kuota 3600, `is_open = false`), 36 baris domisili,
  dan 1 akun admin dari `ADMIN_USERNAME`/`ADMIN_PASSWORD`.
- **Jangan pernah** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, atau `db:wipe`
  di produksi. `AppServiceProvider::boot()` sudah menolaknya, tapi jangan diakali
  dengan `--force`: perintah itu menghapus seluruh pendaftaran peserta.
- Jadwal acara, venue, dan jendela pendaftaran **tidak** diisi seeder. Isi lewat
  panel admin (`/admin/events`) setelah login, sebelum pendaftaran dibuka.

Kalau rilis berikutnya menambah migration, cukup `php artisan migrate --force`.

### Pemulihan admin (tidak ada admin aktif)

`AdminUserSeeder` memakai `firstOrCreate`, jadi menjalankannya ulang **tidak** bisa
mengaktifkan kembali atau mereset admin yang sudah ada. Kalau semua admin ternyata
nonaktif (mis. dua admin saling menonaktifkan bersamaan), pulihkan lewat salah satu jalur:

**A. Hosting punya SSH — `tinker`:**

```bash
php artisan tinker
>>> App\Models\User::where('username', 'NAMA_ADMIN')->update(['is_active' => true]);
```

Untuk sekalian mengganti kata sandi (min. 12 karakter):

```
>>> $u = App\Models\User::where('username', 'NAMA_ADMIN')->first();
>>> $u->password = Illuminate\Support\Facades\Hash::make('KATA-SANDI-BARU-MIN-12'); $u->save();
```

**B. Tanpa SSH — phpMyAdmin:** buka database aplikasi, tab SQL, jalankan:

```sql
UPDATE users SET is_active = 1 WHERE username = 'NAMA_ADMIN' AND role = 'admin';
```

Kata sandi **bisa** ikut diganti lewat phpMyAdmin, mis. untuk kasus hanya ada satu admin
dan kata sandinya lupa. Hash bcrypt tidak ditulis tangan, tapi dibuat di laptop:

1. Di laptop (PHP terpasang), buat hash dari kata sandi baru — **minimal 12 karakter**:

   ```bash
   php -r "echo password_hash('KATA-SANDI-BARU-MIN-12', PASSWORD_BCRYPT), PHP_EOL;"
   ```

   Hasilnya berawalan `$2y$10$...` (60 karakter). Kalau shell Anda memakai tanda kutip
   ganda untuk perintah ini dan kata sandinya memuat `$`, `"`, atau `\`, hindari karakter
   itu supaya tidak terpotong shell.

2. Di phpMyAdmin, tab SQL, tempel hash itu ke `password` sekaligus aktifkan akunnya:

   ```sql
   UPDATE users
   SET password = '$2y$10$HASIL-DARI-LANGKAH-1', is_active = 1
   WHERE username = 'NAMA_ADMIN' AND role = 'admin';
   ```

   Pastikan hash tertempel utuh (60 karakter, tanpa spasi/baris baru di ujung).
   Format `$2y$` yang dihasilkan `password_hash` kompatibel dengan `Hash::check` Laravel.

3. Login dengan kata sandi baru, lalu **segera ganti lewat `/admin/users`** (edit akun
   sendiri) dengan kata sandi yang tidak pernah lewat laptop/riwayat shell. Hapus juga
   riwayat perintah di laptop kalau kata sandi sementara itu sempat diketik di sana.

Setelah itu login dan pastikan `/admin/users` menampilkan minimal satu admin aktif.

## 6. Cache (tiap rilis)

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Atau sekaligus: `php artisan optimize`.

Catatan penting:

- **Setelah `config:cache`, `.env` tidak dibaca lagi saat runtime.** Setiap kali
  `.env` diubah, jalankan ulang `php artisan config:cache` — kalau tidak, perubahan
  itu tidak berpengaruh sama sekali.
- Konsekuensi yang sudah diperhitungkan: `bootstrap/app.php` memakai `env('APP_ENV')`
  untuk memutuskan `trustProxies`. Dengan config ter-cache nilainya `null`, jadi
  proxy tidak dipercaya — aman secara default. Lihat langkah 8 kalau produksi memang
  di belakang Cloudflare.
- `route:cache` aman di sini: satu-satunya rute closure (`/_styleguide`) hanya
  didaftarkan saat `APP_ENV=local`.
- Setelah deploy yang mengubah kode job/notifikasi, jalankan `php artisan queue:restart`
  supaya worker berikutnya memuat kode baru.

Urutan rilis berikutnya secara ringkas:

```bash
cd ~/ticketify
php artisan down            # opsional, kalau perubahannya besar
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

## 7. Cron

Satu entri cron per menit (`docs/hosting.md`):

```
* * * * * cd /home/USER/ticketify && php artisan schedule:run >> /dev/null 2>&1
```

`routes/console.php` yang menjalankan worker singkat:

```php
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping(5);
```

- Pakai path PHP CLI yang benar (beberapa hosting butuh `/usr/local/bin/php83`, bukan
  `php`). Cek dengan `which php` dan `php -v` — versi CLI kadang berbeda dari versi web.
- **Verifikasi:** daftar satu peserta uji, lalu dalam 1–2 menit periksa
  `notification_logs` — statusnya harus berubah dari `pending` ke `sent`. Kalau tetap
  `pending`, cron tidak jalan atau pakai binary PHP yang salah.
- Kalau hosting hanya mengizinkan cron tiap 5 menit, notifikasi tetap terkirim, hanya
  lebih lambat; sampaikan ke panitia supaya tidak dikira gagal.

## 8. HTTPS, proxy, dan IP asli

### HTTPS

Wajib, bukan opsional: halaman scanner memakai kamera HP, dan browser menolak membuka
kamera di halaman non-HTTPS (`docs/arsitektur.md` Fase 4).

1. Aktifkan SSL untuk domain lewat panel hosting (Let's Encrypt/AutoSSL).
2. `public/.htaccess` sudah memaksa redirect ke `https://`. Kalau domain sementara
   belum punya sertifikat, beri komentar pada blok "Paksa HTTPS" supaya tidak terjadi
   redirect loop — lalu aktifkan lagi begitu SSL terpasang.
3. **Verifikasi:** buka `http://DOMAIN/scanner` dari HP — harus berpindah ke `https://`,
   dan tombol "Mulai kamera" harus memunculkan izin kamera, bukan peringatan HTTPS.

### HSTS (langkah lanjutan — JANGAN dipasang sebelum HTTPS stabil)

Header `Strict-Transport-Security` sengaja **belum** ada di `public/.htaccess`. Pasang
hanya setelah HTTPS terbukti stabil beberapa hari (sertifikat terpasang dan
auto-renew jalan, tidak ada halaman/aset yang masih `http://`, scanner di HP normal).

Risikonya: begitu browser menerima HSTS, ia menolak membuka domain lewat `http://` dan
menolak melewati peringatan sertifikat selama `max-age`. Kalau sertifikat kedaluwarsa
atau SSL dicabut, situs **tidak bisa dibuka sama sekali** dari browser yang sudah
menyimpannya, dan tidak ada tombol "lanjutkan" untuk peserta atau petugas. Efeknya
tidak bisa ditarik dari sisi server, hanya menunggu `max-age` habis. Karena itu:

1. Mulai dengan `max-age` pendek, mis. `300` (5 menit), lalu naikkan bertahap (1 hari,
   1 minggu, 1 bulan).
2. Jangan tambahkan `includeSubDomains` atau `preload` kecuali seluruh subdomain sudah
   HTTPS; `preload` praktis tidak bisa dibatalkan.
3. Contoh di `public/.htaccess`, dalam `<IfModule mod_headers.c>`:
   `Header always set Strict-Transport-Security "max-age=300"`
4. Jangan menaikkan `max-age` menjelang hari acara; risiko kegagalan sertifikat di saat
   itu tidak sebanding manfaatnya.

### Proxy & IP asli (lanjutan catatan Fase 7)

Fase 7 menambahkan `trustProxies(at: '*')` di `bootstrap/app.php` **hanya** untuk
`APP_ENV=local`, demi uji kamera lewat Cloudflare Tunnel. Di produksi, jawabannya
tergantung satu pertanyaan:

**Apakah DNS domain produksi diproxy Cloudflare (awan oranye)?**

**Tidak** (DNS abu-abu / langsung ke hosting) → **jangan aktifkan `trustProxies` sama
sekali**. Tanpa proxy, `REMOTE_ADDR` sudah IP asli peserta. Tidak ada yang perlu diubah;
kondisi `APP_ENV=local` yang ada sekarang sudah benar.

**Ya** → forwarded header perlu dipercaya, tapi **tidak boleh dengan `'*'`**. `'*'`
berarti Laravel mempercayai `X-Forwarded-For` dari siapa pun yang bisa menjangkau
origin, sehingga:

- throttle per IP (`POST /daftar` 300/menit lewat limiter `daftar-submit`, dan
  `POST /cari-tiket` `throttle:120,1`) bisa ditembus hanya dengan mengganti-ganti header; dan
- `registrations.ip_address` serta `scan_logs.ip_address` jadi tidak bisa dipercaya saat
  menelusuri pendaftaran mencurigakan.

Yang benar: isi `at:` dengan daftar rentang IP Cloudflare saja. Ganti blok di
`bootstrap/app.php` menjadi:

```php
$middleware->trustProxies(at: env('APP_ENV') === 'local' ? '*' : [
    // https://www.cloudflare.com/ips/ — SALIN ULANG saat deploy.
    '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
    '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
    '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
    '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
    '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
    '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
]);
```

Bentuk ini aman terhadap `config:cache` (nilainya literal, tidak bergantung `.env`) dan
tidak merusak `tests/Feature/TrustedProxyTest.php` — request dari `127.0.0.1` tetap di
luar rentang Cloudflare, jadi forwarded header-nya tetap diabaikan.

Rentangnya jarang berubah, tapi **wajib disalin ulang dari
<https://www.cloudflare.com/ips/> pada hari deploy**; daftar di atas bisa sudah basi.

### Kunci origin ke Cloudflare

Daftar rentang di atas tidak menolong kalau origin masih bisa dihubungi langsung lewat
IP hostingnya — penyerang tinggal melewati Cloudflare dan mengirim forwarded header dari
alamat lain. Shared hosting tidak memberi akses firewall server, jadi pembatasannya
dilakukan di level Apache: blok `Require ip` yang sudah disiapkan (dalam keadaan
dikomentari) di bagian bawah `public/.htaccess`. Aktifkan bersamaan dengan langkah di
atas, setelah menyalin ulang daftar rentangnya.

Alternatif yang lebih kuat kalau hosting mendukungnya: Cloudflare Authenticated Origin
Pulls (origin hanya menerima koneksi TLS yang membawa sertifikat klien Cloudflare).

**Verifikasi IP asli — tidak boleh dilewati:** buka satu halaman publik dari HP lewat
domain produksi (matikan Wi-Fi supaya memakai IP seluler), daftar satu peserta uji, lalu
bandingkan `registrations.ip_address` di database dengan IP asli perangkat penguji
(cek di <https://ifconfig.me>). Kalau yang tercatat justru IP Cloudflare
(mis. `172.x`, `104.x`), berarti `at:` belum benar.

**Verifikasi kunci origin:** buka `https://IP-ORIGIN/` langsung — harus 403. Lewat
domain (via Cloudflare) harus normal.

## 9. Setelah deploy — smoke test

Jalankan urutan singkat ini sebelum menyatakan rilis selesai:

1. `https://DOMAIN/` tampil (atau halaman Status, kalau `is_open` masih `false`).
2. Login admin di `https://DOMAIN/login`, buka `/admin` — dashboard tampil.
3. Isi jadwal & venue di `/admin/events`, buka `is_open`.
4. Daftar satu peserta uji dari HP. Halaman sukses muncul, e-ticket diterima
   (atau tercatat di `storage/logs` kalau `TICKET_NOTIFIER` masih `log`).
5. Buka `/tiket/{token}` — QR tampil, email/nomor tersamar.
6. Login scanner di HP, scan QR peserta uji dengan kamera → hijau "SERAHKAN N GELANG".
7. Scan ulang QR yang sama → merah "SUDAH DITUKAR".
8. **Batalkan registrasi uji** lewat `/admin/registrations/{id}` supaya kuota kembali
   dan data uji tidak ikut terhitung di hari H.

Uji lengkapnya ada di `docs/uji-e2e.md`. Jalankan itu, bukan hanya daftar di atas,
sebelum hari H.

---

## Checklist H-1

Dikerjakan sehari sebelum acara, bersama panitia.

### Data & cadangan

- [ ] Export CSV peserta dari `/admin/export`, simpan salinan offline (laptop petugas
      + cetak). Ini cadangan hari H kalau internet venue mati — kolomnya kode, nama,
      HP, jumlah tiket.
- [ ] Dump database (`mysqldump` atau phpMyAdmin → Export), simpan di luar server.
- [ ] Pastikan tidak ada registrasi uji yang tersisa: cari nama/kode uji di
      `/admin/registrations`, batalkan kalau masih ada.
- [ ] Catat angka pembanding dari dashboard: jumlah pendaftar, tiket terpakai, sisa.

### Konfigurasi

- [ ] `TICKET_NOTIFIER=mail` (bukan `log`) dan `MAIL_MAILER=smtp` — kalau masih `log`,
      tidak ada peserta yang menerima e-ticket. Uji dengan tombol "Kirim ulang" di satu
      registrasi dan pastikan emailnya benar-benar masuk (kotak masuk, bukan spam) dan
      link-nya `https://DOMAIN/tiket/...`.
- [ ] SPF, DKIM, DMARC domain pengirim lolos (langkah 4, "Email pengirim").
- [ ] Batas kirim harian/per jam mailbox sudah dicek dan cukup untuk sisa pendaftar +
      kirim ulang.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`.
- [ ] `php artisan config:cache` sudah dijalankan setelah perubahan `.env` terakhir.
- [ ] `notification_logs` tidak menumpuk status `pending` atau `failed`
      (cek `/admin/registrations` → detail peserta). Kalau banyak `failed`, periksa
      kredensial SMTP/WA sebelum hari H, bukan pada hari H. `last_error` sudah disaring
      (email/nomor/kredensial diganti `[email]`/`[nomor]`/`[disaring]`), jadi aman dibaca
      tapi tidak memuat alamat tujuan — cocokkan lewat kode registrasi.
- [ ] Antrean `jobs` kosong dan `failed_jobs` kosong.
- [ ] Cron `schedule:run` masih aktif (daftar peserta uji baru, pastikan `sent` < 2 menit).

### Akun & perangkat petugas

- [ ] Akun `scanner` dibuat untuk setiap petugas (satu akun per orang, supaya
      `scan_logs.user_id` dan "ditukar oleh" bermakna). Jangan pakai akun bersama.
- [ ] Setiap petugas sudah mencoba login di perangkat yang akan dipakai besok.
- [ ] **Uji alat scanner fisik**: colok ke laptop, buka `/scanner`, pilih mode
      "Alat scanner", scan satu QR uji → langsung tertukar tanpa konfirmasi. Pastikan
      alatnya mode keyboard (HID) dan mengirim `Enter` di akhir.
- [ ] **Uji kamera di HP petugas** (bukan hanya HP developer): buka `/scanner` lewat
      HTTPS, pilih mode "Kamera", izinkan akses kamera, scan QR uji dari layar HP lain.
      Cek juga di kondisi cahaya seperti di venue.
- [ ] Uji pencarian manual: cari peserta uji dengan kode, nama, dan nomor HP.
- [ ] Uji suara & getar scanner menyala dan terdengar di keramaian; tentukan mau
      dinyalakan atau dimatikan.
- [ ] Baterai/power bank untuk HP petugas, dan kabel untuk laptop alat scanner.

### Jaringan & rencana cadangan

- [ ] Cek sinyal seluler di titik penukaran gelang. Kalau lemah, siapkan hotspot
      cadangan atau posisikan meja di titik bersinyal.
- [ ] Sepakati prosedur kalau internet mati: pakai CSV cetak, catat manual kode yang
      sudah dilayani, lalu rekonsiliasi setelah online. **Jangan** menyerahkan gelang
      tanpa mencatat kode.
- [ ] Sepakati prosedur untuk panel ungu "TIKET DIBATALKAN": arahkan ke meja bantuan,
      jangan diserahkan gelangnya.
- [ ] Nomor kontak admin yang bisa membuka `/admin` dari HP saat acara berlangsung.

### Penutupan pendaftaran

- [ ] Tentukan kapan `is_open` dimatikan (atau `registration_close_at` diisi), dan siapa
      yang mengeksekusi.
- [ ] Setelah ditutup, cek `https://DOMAIN/daftar` menampilkan halaman Status, dan
      `/cari-tiket` masih bisa dipakai peserta yang kehilangan e-ticket.
