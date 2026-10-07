# Desain halaman peserta — Opening Porprov Jateng XVII 2026 (Ticketify)

Dokumen ini adalah acuan visual halaman publik (peserta). Halaman admin dan scanner memakai Metronic dan TIDAK mengikuti dokumen ini.

## Sumber kebenaran

- `artboards/*.dc.html` adalah desain final. Nilai warna, ukuran font, jarak, radius, dan teks di file itu adalah nilai yang harus dipakai. Jangan menebak atau "merapikan" nilai.
- `Main`, `Form`, `Sukses`, `Tiket`, `LupaTiket`, `Status` = versi mobile (lebar 390 px).
- `Desktop*` = versi desktop (lebar 1280 px) dari halaman yang sama.
- `screenshots/` berisi gambar tiap artboard untuk perbandingan visual.

### Cara membaca file .dc.html

File artboard memakai format editor desain, BUKAN Blade. Terjemahkan, jangan disalin mentah:

| Di artboard | Di Laravel |
|---|---|
| `<x-dc>`, `<helmet>`, `support.js`, `data-props`, `class Component extends DCLogic` | Buang. Hanya untuk editor desain. |
| `{{nama}}` | Data dari controller (`{{ $nama }}`) atau state JS |
| `<sc-if value="{{x}}">` | `@if` di Blade, atau toggle `hidden` via JS untuk state di browser |
| `onClick="{{inc}}"` dan `this.setState` | Vanilla JS (`addEventListener`) |
| Tweak "Simulasi" (`maxQty`, `errorFromServer`, `status`, `kondisi`) | Kondisi nyata dari backend, lihat tabel halaman di bawah |
| `href="Form.dc.html"` | `route(...)` yang sesuai |
| Inline `style="..."` | Pindahkan ke class di `public/css/app.css` memakai token di bawah |
| Placeholder `[TANGGAL & JAM ACARA]`, `[LOKASI ACARA]`, `[TANGGAL & JAM BUKA]`, `[JAM PENUKARAN]` | Data dari tabel `events` / `registrations`, bukan teks statis |
| Kotak `[Verifikasi Cloudflare Turnstile]` | Widget Turnstile asli |
| SVG QR contoh | QR SVG yang di-generate dari `registrations.token` |

## Token

Tulis sebagai CSS custom properties di `:root` dalam `public/css/app.css`.

### Warna

| Token | Hex | Dipakai untuk |
|---|---|---|
| `--navy` | `#0E2A6B` | Tombol utama, judul, lingkaran nomor langkah, latar halaman e-ticket, panel kiri form desktop |
| `--blue` | `#1F5FD6` | Link, eyebrow ("Pendaftaran penonton") |
| `--blue-hover` | `#123F9E` | Hover link |
| `--ground` | `#EEF3FB` | Latar halaman, kotak info, chip |
| `--surface` | `#FFFFFF` | Kartu, header |
| `--ink` | `#0B1B3F` | Teks utama |
| `--muted` | `#4B5A7A` | Teks sekunder, helper |
| `--line` | `#D6DEEC` | Garis pemisah, sobekan tiket |
| `--input-border` | `#C5D0E3` | Border input dan stepper |
| `--disabled-bg` / `--disabled-ink` | `#F1F4F9` / `#8C98B0` | Tombol stepper nonaktif |
| `--on-navy` / `--on-navy-muted` | `#D5E0F8` / `#B9CCF5` | Teks di atas latar navy |
| `--success` | `#179C52` | Lingkaran centang sukses, langkah terakhir |
| `--success-bg` / `--success-ink` | `#E3F4EA` / `#0F6A3B` | Badge "Sudah ditukar" |
| `--info-bg` / `--info-ink` | `#E6EEFC` / `#123F9E` | Badge "Belum ditukar", pesan netral cari tiket |
| `--warn-bg` / `--warn-ink` | `#FFF1DC` / `#7A3E00` | Banner error kuota dari server |
| `--error` | `#B42318` | Pesan "Sisa kuota tinggal N tiket" di bawah stepper |
| `--gelang-bg` / `--gelang-ink` | `#FFF6D6` / `#3D2E00` | Kotak "4 tiket = 4 gelang" |
| Garis Porprov | `#179C52`, `#F2B705`, `#D93A2B`, `#1F5FD6` | Strip 4 warna di puncak setiap halaman (6 px), dan di dasar panel navy |

### Tipografi

- Font utama: **Plus Jakarta Sans** (400, 500, 600, 700, 800), fallback `system-ui, sans-serif`.
- Font aksen: **Caveat** 700, HANYA untuk tagline "Ngopeni Nglakoni Menuju Puncak Prestasi Jawa Tengah" di beranda, diputar `-2deg`. Teksnya panjang sehingga membungkus jadi dua baris; `text-wrap: balance` menyeimbangkan barisnya (28 px mobile, 38 px desktop, tidak berubah).
- Muat dari Google Fonts lewat `<link>` di layout. Tidak ada font lain.

| Elemen | Mobile | Desktop | Weight |
|---|---|---|---|
| H1 beranda | 34 px, line-height 1.12, letter-spacing -0.02em | 58 px, line-height 1.05, letter-spacing -0.025em | 800 |
| H1 halaman lain | 26–28 px | 30–32 px | 800 |
| Tagline Caveat | 28 px | 38 px | 700 |
| Kode registrasi | 22–24 px, letter-spacing 0.04–0.05em | 26 px | 800 |
| Body | 15 px, line-height 1.55 | 16 px | 400 |
| Label input | 14 px | 14 px | 700 |
| Helper | 13 px | 13–14 px | 400 |
| Tombol utama | 17 px | 17–18 px | 700 |

### Bentuk dan jarak

- Radius: tombol & input 12–14 px, kartu 20–28 px, chip & badge 999 px, lingkaran nomor 50%.
- Tinggi: tombol utama 56 px (desktop hero 58 px), input 50–52 px, stepper 52 px, target sentuh minimal 44 px.
- Padding halaman: mobile 16–24 px sisi; desktop 80 px sisi.
- Tidak ada shadow. Pemisahan hanya lewat perbedaan latar (`--ground` vs `--surface`).

## Komponen (jadikan Blade component/partial)

- **Strip Porprov**: grid 4 kolom sama lebar, tinggi 6 px, di puncak setiap halaman.
- **Bar logo** (`<x-logo-bar>`, di layout publik): bar putih tepat di bawah strip Porprov di SEMUA halaman peserta, termasuk e-ticket berlatar navy. Satu baris, tiga kelompok: kiri logo Porprov; tengah logo Jawa Tengah + KONI Jateng berdampingan; kanan logo Ngopeni Nglakoni Jateng. Desktop (≥ 992 px): tinggi logo Porprov 56 px, lainnya 48 px, padding sisi 80 px, jarak logo tengah 16 px. Mobile: Porprov 32 px, lainnya 28 px, padding sisi 16 px, jarak logo tengah 8 px. `<picture>` WebP + fallback PNG dengan `width`/`height` asli berkas; berkas di `public/img/logo-*.{webp,png}`.
- **Header** (di bawah bar logo, tanpa logo): mobile = tombol Kembali saja (halaman tanpa tombol Kembali tidak punya header mobile); desktop = bar putih 64 px, link/tombol rata kanan.
- **Tombol utama**: latar `--navy`, teks putih. **Tombol sekunder**: border 1.5 px `--input-border` atau `--navy`, teks navy.
- **Field**: label di atas, input, helper di bawah.
- **Stepper jumlah tiket**: `−` [angka] `+`, lihat aturan di bawah.
- **Alert**: banner kuota (warn), status netral (info).
- **Tiket**: kartu putih dengan sobekan (lingkaran warna latar di kedua sisi + garis putus-putus). Mobile = sobekan horizontal; desktop = vertikal.
- **Langkah bernomor**: lingkaran navy 36/40 px berisi angka; langkah terakhir lingkaran hijau berisi centang.
- Ikon: SVG garis (stroke 2) inline, bukan icon font, bukan emoji.

## Halaman dan kondisi nyata

| Artboard | Route (usulan) | Kondisi dari backend |
|---|---|---|
| Main / DesktopMain | `/` | Jika pendaftaran belum dibuka, ditutup, atau penuh → tampilkan Status |
| Form / DesktopForm | `/daftar` | `maxQty = max(0, min(4, sisa))`; banner kuota muncul hanya jika redirect dari submit dengan error kuota |
| Sukses / DesktopSukses | `/daftar/sukses/{token}` | Kode dan jumlah tiket dari registrasi |
| Tiket / DesktopTiket | `/tiket/{token}` | `redeemed_at` null → badge "Belum ditukar"; terisi → badge "Sudah ditukar" + jamnya, QR diredupkan + label "Gelang sudah diterima" |
| LupaTiket / DesktopLupaTiket | `/cari-tiket` | Setelah submit SELALU tampilkan pesan netral yang sama, terdaftar atau tidak |
| Status / DesktopStatus | dipakai oleh `/` dan `/daftar` | `belum dibuka` / `ditutup` / `kuota penuh`; tombol "Cari tiket saya" tidak tampil saat belum dibuka |

## Field email (nama + dropdown domain)

- Satu kotak berisi: input nama email (`inputmode="email"`, `autocomplete="email"`, tanpa autocapitalize), tanda `@`, dan `<select>` domain lebar 136 px dengan latar `#F6F8FC`.
- Pilihan domain, urutan tetap: `gmail.com` (default), `yahoo.com`, `yahoo.co.id`, `outlook.com`, `icloud.com`, `Lainnya…`.
- `Lainnya…` memunculkan input domain bebas di bawahnya (placeholder "contoh: kantor.co.id").
- Di bawahnya selalu tampil pratinjau "Email lengkap: **nama@domain**" supaya peserta bisa melihat salah ketik.
- JS: jika input nama berisi `@` (hasil paste atau autofill), pecah otomatis. Bagian setelah `@` dipilih di dropdown jika ada di daftar, selain itu masuk ke `Lainnya…`.
- Kirim ke server sebagai tiga field: `email_local`, `email_domain`, `email_domain_other`. Server yang menggabungkan (lihat `docs/arsitektur.md`).
- Setelah error validasi, ketiga nilai dikembalikan lewat `old()`.

## Field nomor WhatsApp

- Input biasa, placeholder `08xxxxxxxxxx`. Helper: "Boleh diawali 08 atau 62. E-ticket dikirim ke nomor ini. …". Normalisasi ke `62` di server, bukan di browser.

## Aturan stepper (wajib sama dengan desain)

- Nilai awal 1 (atau `old('ticket_qty')`, dibatasi `maxQty`). Batas bawah 1, batas atas `maxQty` dari server (maks. 4).
- Tombol yang mentok dinonaktifkan (`disabled`) dan berganti warna `--disabled-bg` / `--disabled-ink`.
- Pesan merah "Sisa kuota tinggal N tiket." di bawah stepper HANYA muncul saat `+` mentok DAN `maxQty < 4` DAN tidak ada banner error server.
- Angka sisa kuota tidak boleh tampil di tempat lain, dan tidak boleh dikirim ke frontend selain lewat `maxQty`.

## Responsive

- Mobile-first. Tampilan mobile adalah default; tampilan desktop berlaku mulai **992 px**.
- Di bawah 992 px: semua layout dua kolom (hero beranda, form + panel navy, tiket horizontal) menjadi satu kolom seperti artboard mobile.
- Rentang 768–991 px: field form boleh tetap dua kolom; panel navy form pindah ke atas.
- Langkah "Cara mendaftar": 1 kolom (mobile), 2 kolom (768 px+), 4 kolom (992 px+).
- Konten desktop dibatasi lebar maksimum 1120 px di tengah (1280 − 2 × 80 padding).

## Yang dilarang

- Jangan menambah shadow, gradien, animasi masuk, atau warna di luar token.
- Jangan mengganti font atau memakai font default framework.
- Jangan memakai Tailwind, Vite, atau proses build lain untuk halaman ini; CSS ditulis tangan di satu file statis.
- Jangan menampilkan sisa kuota di awal halaman.
- Logo resmi sudah dipasang (lihat Bar logo); tidak ada lagi logo di dalam header.
