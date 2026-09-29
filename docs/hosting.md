# Hosting

> Isi semua bagian bertanda [ISI] sebelum Fase 1. Claude Code tidak boleh menebak nilai-nilai ini.

## Fakta server

| Item | Nilai |
|---|---|
| Penyedia & paket | [ISI] |
| Versi PHP | [ISI] |
| Versi Laravel yang dipakai | [ISI, sesuaikan dengan versi PHP] |
| Database & versi | [ISI, mis. MariaDB 10.x] — engine InnoDB |
| Akses SSH | [ISI: ya/tidak] |
| Composer di server | [ISI: ya/tidak] |
| Cron per menit | [ISI: ya/tidak] |
| `disable_functions` | [ISI, cek via `php -i`; biasanya `symlink`, `exec`, `proc_open`, `shell_exec`] |
| HTTPS / SSL | [ISI] — wajib, kamera scanner butuh HTTPS |
| Domain | [ISI], document root diarahkan ke `public/` |
| Batas email per jam dari hosting | [ISI] — tidak dipakai untuk email tiket |

## Konsekuensi yang sudah pasti

- **Tanpa build.** Tidak ada `npm run build`. CSS/JS statis di `public/`. Metronic dipakai dari folder asset jadi (`public/metronic/`).
- **Tanpa worker permanen.** Queue driver `database`, dijalankan cron:
  ```
  * * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
  ```
  dan di `routes/console.php`:
  ```php
  Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();
  ```
- **Symlink storage manual.** `php artisan storage:link` memakai fungsi PHP `symlink()` yang biasanya dimatikan. Jika perlu, buat lewat SSH: `ln -s ../storage/app/public public/storage`.
- **Deploy** lewat `git clone`/`git pull` + `composer install --no-dev --optimize-autoloader`. `vendor/` dan `.env` tidak di-commit.

## Layanan luar

| Layanan | Nilai |
|---|---|
| SMTP pihak ketiga | [ISI: penyedia, host, port] — kredensial hanya di `.env` |
| Cloudflare Turnstile | [ISI: site key di `.env`, secret di `.env`] |
| WhatsApp API | Lihat `docs/whatsapp-api.md` |
