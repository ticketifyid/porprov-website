# Hosting

> Dua nilai masih bertanda [ISI] (versi database dan `disable_functions`); cara mengeceknya tertulis di barisnya. Claude Code tidak boleh menebak nilai-nilai ini.

## Fakta server

| Item | Nilai |
|---|---|
| Penyedia & paket | Hostinger, shared hosting (web server LiteSpeed, kompatibel `.htaccess` Apache) |
| Versi PHP | 8.3.33. CLI: `/opt/alt/php83/usr/bin/php` (pakai path ini di SSH dan cron, bukan `php`) |
| Versi Laravel yang dipakai | 13.33 |
| Database & versi | [ISI: hasil `SELECT VERSION();`] — engine InnoDB |
| Akses SSH | Ya, port 65002 |
| Composer di server | Ya, 2.9.8 |
| Cron per menit | Ya, lewat hPanel, dengan path PHP lengkap |
| `disable_functions` | [ISI: hasil `/opt/alt/php83/usr/bin/php -i \| grep disable_functions`] |
| HTTPS / SSL | Aktif — wajib, kamera scanner butuh HTTPS |
| Domain | `porprov-jateng.ticketify.id`, tidak lewat proxy Cloudflare (DNS langsung ke hosting) |
| Batas email per jam dari hosting | Sudah dicek pemilik proyek dan cukup untuk e-ticket |

## Struktur di server

- Aplikasi di `~/domains/ticketify.id/porprov-app` (di luar web root).
- `public_html/porprov/public` adalah symlink ke `porprov-app/public`, dibuat lewat shell (bukan `storage:link`).
- Deploy dari branch `prod`: `git pull` di `porprov-app`, lalu langkah rilis di `docs/deploy.md`.
- Entri cron di hPanel (ganti `USER` dengan nama akun):
  ```
  * * * * * cd /home/USER/domains/ticketify.id/porprov-app && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
  ```

## Konsekuensi yang sudah pasti

- **Tanpa build.** Tidak ada `npm run build`. CSS/JS statis di `public/`. Metronic dipakai dari folder asset jadi (`public/metronic/`).
- **Tanpa worker permanen.** Queue driver `database`, dijalankan cron (entri di atas) dan di `routes/console.php`:
  ```php
  Schedule::command('queue:work --stop-when-empty --max-time=50')
      ->everyMinute()
      ->withoutOverlapping(5);
  ```
  Kunci `withoutOverlapping` dibatasi **5 menit**, bukan default 24 jam: shared hosting biasa membunuh proses yang dianggap terlalu lama, dan proses yang mati tidak sempat melepas kuncinya. Dengan default, satu worker yang dibunuh membuat seluruh notifikasi berhenti sampai kuncinya kedaluwarsa keesokan harinya; dengan 5 menit, cron berikutnya paling lama menunggu 5 menit lalu jalan lagi sendiri.
- **Tanpa proxy Cloudflare.** `trustProxies` tidak diaktifkan di produksi: kondisi `APP_ENV=local` di `bootstrap/app.php` sudah benar (jalur "Tidak" di `docs/deploy.md` bagian 8), dan blok `Require ip` Cloudflare di `public/.htaccess` tetap dikomentari. Turnstile tetap layanan Cloudflare, terpisah dari proxy DNS.
- **Symlink storage manual.** `php artisan storage:link` memakai fungsi PHP `symlink()` yang biasanya dimatikan. Jika perlu, buat lewat SSH: `ln -s ../storage/app/public public/storage`.
- **Deploy** lewat `git clone`/`git pull` + `composer install --no-dev --optimize-autoloader`. `vendor/` dan `.env` tidak di-commit.

## Layanan luar

| Layanan | Nilai |
|---|---|
| SMTP pihak ketiga | SMTP Hostinger, pengirim `noreply@ticketify.id`; SPF/DKIM/DMARC PASS. Host, port, dan kredensial hanya di `.env` |
| Cloudflare Turnstile | Widget Managed untuk `porprov-jateng.ticketify.id`; site key dan secret hanya di `.env`. Cadangan: captcha gambar (`App\Services\ImageCaptcha`) |
| WhatsApp API | Lihat `docs/whatsapp-api.md` |
