# Ticketify: Registrasi Opening Porprov Jateng XVII 2026

Web registrasi penonton + e-ticket QR + penukaran gelang untuk Opening Ceremony Porprov Jateng XVII 2026, di platform Ticketify. Laravel di shared hosting. WhatsApp API berjalan di VPS terpisah milik pemilik proyek.

Kuota 3.600 tiket. Maksimal 4 tiket per pendaftaran. 1 pendaftaran = 1 QR, ditukar sekaligus dengan semua gelangnya.

## Dokumen acuan (baca sebelum mengerjakan bagian terkait)

- `docs/arsitektur.md`: flow lengkap, keputusan, aturan bisnis. WAJIB dibaca sebelum menyentuh pendaftaran, kuota, notifikasi, tiket, atau scan.
- `docs/erd.md`: spesifikasi tabel. Migration harus persis mengikuti ini.
- `docs/hosting.md`: batasan shared hosting.
- `docs/whatsapp-api.md`: kontrak API WhatsApp di VPS. BELUM dipakai; notifikasi asli dikerjakan pemilik proyek di akhir.
- `docs/design/DESIGN.md` + `docs/design/artboards/`: desain halaman peserta. Nilai visual harus persis.
- `docs/prompts.md`: urutan fase pengerjaan.

## Stack

- Laravel (versi di `docs/hosting.md`), PHP, MySQL/MariaDB InnoDB.
- Halaman peserta: Blade + CSS tulis tangan (`public/css/app.css`) + vanilla JS. Tanpa Bootstrap, Tailwind, Vite, atau build.
- Halaman admin & scanner: Blade + Metronic (asset sudah jadi di `public/metronic/`). Tanpa build.
- Queue: driver `database`, dijalankan cron (`schedule:run` tiap menit memanggil `queue:work --stop-when-empty --max-time=50`).

## Aturan inti (tidak boleh dilanggar)

1. **Kuota dicek di dalam lock.** Submit pendaftaran: `DB::transaction` → `Event::lockForUpdate()` → hitung `sisa = quota - tickets_taken` → tolak atau `increment('tickets_taken', qty)` + insert. Tidak ada cara lain.
2. **Sisa kuota tidak dikirim ke frontend.** View form hanya menerima `maxQty = max(0, min(4, sisa))`. Pesan "Sisa kuota tinggal N tiket" hanya muncul saat user mentok di batas kuota atau saat submit ditolak.
3. **Pesan kuota:** `sisa <= 0` → "Mohon maaf, kuota pendaftaran sudah penuh." ; `qty > sisa` → "Sisa kuota tinggal {sisa} tiket. Silakan kurangi jumlah tiket." ; kembali dengan `back()->withInput()`.
4. **`ticket_qty` divalidasi server** `integer|between:1,4`. Batas di HTML hanya UX.
5. **Nomor HP dinormalisasi** ke `62xxx` (buang non-digit; `08` → `628`; `+62` → `62`) sebelum validasi unique dan sebelum disimpan, di SATU helper yang dipakai semua tempat.
6. **Kode registrasi** `{events.code_prefix}-{6 karakter}`, contoh `PJT26-7K3M9Q`. Alfabet Crockford Base32 `0123456789ABCDEFGHJKMNPQRSTVWXYZ`, dari `random_int`, retry jika bentrok. Dibuat sebelum insert.
7. **Token QR** `Str::random(48)`, unique. QR dan link tiket (`/tiket/{token}`) memakai token, BUKAN kode registrasi.
8. **Notifikasi dikirim setelah COMMIT** lewat job queue, tidak pernah di dalam transaksi atau sinkron di request. Job HANYA memanggil interface `App\Contracts\TicketNotifier`. Implementasi WA dan email asli dikerjakan sendiri oleh pemilik proyek di akhir; sampai saat itu binding-nya `LogTicketNotifier` (`TICKET_NOTIFIER=log`). Jangan membuat implementasi WA atau email asli.
9. **Penukaran gelang** memakai update bersyarat `WHERE id = ? AND redeemed_at IS NULL`, cek affected rows. Setiap scan dicatat di `scan_logs` beserta `method`.
10. **Dua mode scan:** `camera` (HP) = dua langkah, scan lalu konfirmasi. `hardware` (alat scanner mode keyboard di laptop) = sekali scan langsung tertukar. Pencarian manual = dua langkah.
11. **Cari tiket** selalu membalas pesan netral yang sama, terdaftar atau tidak. Link hanya dikirim ke kontak terdaftar, tidak ditampilkan di layar.
12. **Peserta tidak punya akun.** Hanya `users` dengan role `admin` / `scanner`.

## Dilarang

- `@vite(...)`, `package.json`, `npm`, Tailwind, Livewire, Inertia, Vue, React.
- `php artisan storage:link` (lihat `docs/hosting.md`).
- Menampilkan angka sisa kuota di awal halaman, atau lewat endpoint API.
- Memakai `id` atau kode registrasi berurutan sebagai isi QR.
- Mengubah nilai desain (warna, ukuran, jarak, teks) dari `docs/design/` tanpa diminta.
- Menambah package Composer tanpa bertanya dulu.

## Cara kerja

- Kerjakan satu fase dari `docs/prompts.md` per sesi. Selesaikan dengan tes lulus dan ringkasan perubahan.
- Tulis feature test untuk aturan inti SEBELUM implementasinya.
- Jika aturan di sini bertabrakan dengan cara standar Laravel, aturan di sini yang menang. Sebutkan tabrakannya, jangan diam-diam mencari jalan pintas.
- Jika sesuatu tidak tercakup dokumen, tanya. Jangan mengarang kontrak API, nilai konfigurasi, atau keputusan bisnis.
- Pesan UI dalam Bahasa Indonesia, sapaan "Anda".

## Perintah

- Tes: `php artisan test`
- Migrasi ulang + seed: `php artisan migrate:fresh --seed`
- Server lokal: `php artisan serve`
- Worker queue lokal: `php artisan queue:work`
