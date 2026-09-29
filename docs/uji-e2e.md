# Uji manual end-to-end

Daftar uji yang dijalankan **sebelum hari H**, di lingkungan produksi (atau staging yang
persis sama), dengan perangkat yang benar-benar akan dipakai. Ditulis pada Fase 9
(`docs/prompts.md`); pasangannya `docs/deploy.md`.

Tes otomatis (`php artisan test`) sudah menutup aturan inti di level kode. Dokumen ini
menutup yang tidak bisa diuji otomatis: kamera HP, alat scanner fisik, HTTPS, jaringan
buruk, dan alur yang dijalankan orang sungguhan.

## Cara memakai

- Kerjakan berurutan: bagian B dan C bergantung pada data yang dibuat di bagian A.
- Setiap langkah punya **hasil yang diharapkan**. Kalau berbeda sedikit pun, hentikan
  dan catat — jangan lanjut.
- Pakai data uji yang mudah dikenali (nama diawali `UJI`, mis. `UJI Budi Satu`) supaya
  gampang dibersihkan.
- **Setelah selesai, bersihkan**: batalkan semua registrasi `UJI` lewat panel admin
  (bagian E) supaya kuota kembali dan angka dashboard bersih.

## Persiapan

| Item | Keterangan |
|---|---|
| Perangkat 1 | HP petugas (yang akan dipakai besok), browser Chrome/Safari terbaru |
| Perangkat 2 | HP "peserta" untuk menampilkan QR di layar |
| Perangkat 3 | Laptop + alat scanner fisik (mode keyboard/HID) |
| Akun | 1 admin, 2 scanner (`UJI-petugas-a`, `UJI-petugas-b`) |
| Event | `is_open = true`, jadwal & venue sudah diisi, kuota masih tersisa |

Catat sebelum mulai: **tiket terpakai** dan **sisa** di dashboard admin.

---

## A. Alur peserta

Dijalankan dari HP, memakai koneksi seluler (bukan Wi-Fi kantor), lewat domain produksi.

### A1. Beranda

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka `http://DOMAIN` (sengaja `http`) | Berpindah otomatis ke `https://DOMAIN`, gembok muncul di address bar |
| 2 | Lihat halaman beranda | Nama acara, tanggal, dan venue sesuai yang diisi admin. Tombol "Daftar sekarang" terlihat tanpa perlu scroll |
| 3 | Lihat seluruh halaman | **Tidak ada** angka sisa kuota di mana pun |
| 4 | Buka "View source" / Inspect | Tidak ada angka sisa kuota di HTML, juga tidak ada request API yang mengembalikannya |
| 5 | Putar HP ke landscape, lalu buka di layar laptop | Tata letak tetap rapi, tidak ada scroll horizontal |

### A2. Form pendaftaran

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Tekan "Daftar sekarang" | Form terbuka, kolom Nama fokus/terlihat |
| 2 | Tekan Daftar dengan semua kolom kosong | Form kembali dengan pesan per kolom dalam Bahasa Indonesia ("Nama lengkap wajib diisi.", dst). Halaman **tidak** terlempar ke beranda |
| 3 | Isi nama, pilih domisili dari daftar | Daftar berisi 35 kab/kota Jawa Tengah + "Luar Jawa Tengah" |
| 4 | Paste `Budi.Santoso+1@Gmail.com` ke kotak nama email | Otomatis terpecah: nama email `Budi.Santoso+1`, dropdown memilih `gmail.com` (bukan "Lainnya…"). Pratinjau "Email lengkap" tampil huruf kecil |
| 5 | Pilih "Lainnya…" lalu ketik `KANTOR.CO.ID` | Yang tersimpan di kotak menjadi huruf kecil `kantor.co.id` |
| 6 | Ketik domain `gmail.com` di kotak "Lainnya…" | Saat submit, diperlakukan sama dengan memilih `gmail.com` di dropdown |
| 7 | Isi nomor HP `0812-3456 7890` | Diterima (normalisasi ke `62…` terjadi di server) |
| 8 | Isi nomor HP `12345` | Ditolak: "Nomor WhatsApp tidak valid. Contoh: 081234567890." |
| 9 | Tekan tombol `−` saat jumlah tiket = 1 | Tombol `−` nonaktif, angka tetap 1 |
| 10 | Tekan `+` sampai mentok | Berhenti di 4. Tombol `+` nonaktif. Teks bantuan "Maks. 4 tiket." tetap tampil |
| 11 | Selesaikan Turnstile | Centang/verifikasi muncul dan selesai sendiri |
| 12 | Tekan Daftar tanpa menyelesaikan Turnstile (matikan dulu, atau submit cepat) | Ditolak: "Verifikasi keamanan belum selesai. Coba lagi." |
| 13 | Isi lengkap dan tekan Daftar | Halaman Sukses muncul |

### A3. Halaman sukses & e-ticket

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Baca halaman sukses | Kode registrasi berformat `PJT26-XXXXXX` (6 karakter, tanpa huruf `I`, `L`, `O`, `U`). Jumlah tiket sesuai |
| 2 | Lihat URL halaman sukses | Berisi token panjang acak, **bukan** kode registrasi dan bukan angka berurutan |
| 3 | Tunggu maks. 2 menit, cek WhatsApp | Pesan e-ticket masuk berisi link `/tiket/{token}` |
| 4 | Cek email (termasuk folder spam) | E-ticket masuk |
| 5 | Buka link e-ticket | Halaman tiket navy, QR tampil jelas dan terbaca di kecerahan layar sedang |
| 6 | Baca data di halaman tiket | Email tersamar (`bu***@gmail.com`), nomor tersamar (`0812-****-7890`). Nama, kode, domisili, dan jumlah tiket utuh |
| 7 | Lihat badge status | "Belum ditukar" |
| 8 | Ubah satu huruf token di URL | 404 |
| 9 | Cek `view-source` halaman tiket | Ada `<meta name="robots" content="noindex, nofollow">`. Email dan nomor utuh **tidak** ada di HTML |
| 10 | Screenshot halaman tiket | QR tetap terbaca saat di-scan dari screenshot (ini yang akan dilakukan peserta) |

### A4. Kuota & duplikat

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Daftar lagi dengan email yang sama persis | Ditolak di kolom email: "Email ini sudah dipakai untuk mendaftar. Gunakan menu Cari tiket saya…" |
| 2 | Daftar lagi dengan `b.u.di+lain@googlemail.com` (kanonik sama dengan `budi@gmail.com`) | Ditolak dengan pesan yang sama — duplikat dicek lewat bentuk kanonik |
| 3 | Daftar dengan **nomor HP sama**, email berbeda | **Diterima** — satu nomor boleh dipakai beberapa pendaftaran (keluarga) |
| 4 | Admin: turunkan kuota sampai sisa 2 (lewat `/admin/events`), lalu peserta daftar 4 tiket | Form kembali dengan banner: "Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket." diikuti "Data lain tidak perlu diisi ulang." Semua isian lain masih terisi |
| 5 | Di layar yang sama, tekan `+` pada stepper | Berhenti di 2, dan pesan "Sisa kuota tinggal 2 tiket." muncul di bawah stepper |
| 6 | Kurangi jadi 2 lalu Daftar | Berhasil |
| 7 | Daftar lagi saat sisa 0 | Halaman Status: "Kuota pendaftaran sudah penuh" |
| 8 | Kembalikan kuota ke angka semula lewat `/admin/events` | Form pendaftaran bisa dibuka lagi |

### A5. Pendaftaran tertutup

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Admin matikan `is_open`, peserta buka `/` dan `/daftar` | Halaman Status "Pendaftaran sudah ditutup" (karena `registration_open_at` sudah lewat) |
| 2 | Isi `registration_open_at` ke besok, matikan `is_open`, buka `/daftar` | Status "Pendaftaran belum dibuka" + tanggal buka. Tombol "Cari tiket saya" **tidak** tampil |
| 3 | Saat tertutup, buka `/tiket/{token}` lama | Tetap bisa dibuka — e-ticket lama tidak ikut mati |
| 4 | Kembalikan `is_open` ke semula | — |

### A6. Cari tiket

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka `/cari-tiket`, isi email terdaftar | Pesan netral: "Jika data terdaftar, link e-ticket sudah dikirim ulang ke email dan WhatsApp Anda. Cek juga folder spam." |
| 2 | Cek WhatsApp/email | E-ticket masuk ulang |
| 3 | Isi email yang **tidak** terdaftar | Pesan **persis sama** dengan langkah 1 — tidak ada perbedaan kata, warna, atau waktu muncul yang membocorkan status |
| 4 | Isi nomor HP terdaftar (format `0812…`) | Pesan netral yang sama; e-ticket untuk **semua** pendaftaran dengan nomor itu terkirim |
| 5 | Lihat layar | Link e-ticket **tidak** pernah ditampilkan di halaman, hanya dikirim ke kontak |
| 6 | Ulangi untuk kontak yang sama 5× berturut-turut | Pesan tetap netral dan sama setiap kali; mulai kiriman ke-4 tidak ada pesan baru yang masuk (batas 3/jam per kontak), **tanpa** halaman error |

---

## B. Alur petugas (scanner)

Siapkan tiga registrasi uji yang belum ditukar: **T1** (untuk kamera), **T2** (untuk alat
scanner), **T3** (untuk pencarian manual). Tampilkan QR-nya di Perangkat 2.

### B1. Akses & HTTPS

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka `/scanner` tanpa login | Dilempar ke `/login` |
| 2 | Login sebagai `UJI-petugas-a`, buka `/admin` | Ditolak (403) — scanner tidak boleh masuk panel admin |
| 3 | Login sebagai admin, buka `/scanner` | Boleh — admin punya akses scanner |
| 4 | Buka `/scanner` lewat `http://` | Berpindah ke `https://` |
| 5 | Lihat halaman scanner di HP | Tanpa sidebar. Header tipis berisi nama + role petugas, tombol suara, tombol Keluar. Area hasil besar |
| 6 | Nonaktifkan `UJI-petugas-b` di panel admin, lalu coba login dengan akun itu | "Akun Anda sudah dinonaktifkan. Hubungi administrator." |
| 7 | Salah password 6× | Mulai percobaan ke-6: "Terlalu banyak percobaan masuk. Silakan coba lagi dalam N detik." |

### B2. Mode Kamera (HP, lewat HTTPS) — dua langkah

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Pilih mode "Kamera" | Browser meminta izin kamera |
| 2 | Izinkan | Pratinjau kamera tampil |
| 3 | Arahkan ke QR **T1** | Panel biru "Periksa data": nama, domisili, kode, jumlah gelang. Ada tombol "Serahkan N gelang" dan "Batal" |
| 4 | **Jangan tekan apa-apa**, cek status T1 di panel admin | Masih "Belum ditukar" — langkah pertama kamera tidak mengubah apa pun |
| 5 | Tekan "Batal", lalu scan T1 lagi dan tekan "Serahkan N gelang" | Panel **hijau** besar: "Serahkan **N** gelang" + identitas peserta. Ada bunyi & getar |
| 6 | Cek T1 di panel admin | "Sudah ditukar", jam dan nama petugas terisi |
| 7 | Scan T1 lagi dengan akun yang sama, dalam 2 menit | Panel **netral** (bukan merah): "Baru saja Anda tukar pukul HH.MM (N gelang)" |
| 8 | Login `UJI-petugas-b` di perangkat lain, scan T1 | Panel **merah**: "Sudah ditukar", pukul HH.MM, oleh nama petugas pertama |
| 9 | Cek T1 di panel admin | Jam penukaran **tidak berubah** — tetap penukaran pertama |
| 10 | Scan QR acak / QR apa pun yang bukan tiket | Panel **abu-abu**: "QR tidak dikenal" + "Coba pencarian manual di bawah." |
| 11 | Scan sambil HP miring / QR agak jauh | Tetap terbaca, atau tidak bereaksi sama sekali — tidak boleh salah membaca jadi tiket lain |
| 12 | Kunci layar HP lalu buka lagi | Kamera bisa dilanjutkan (kalau berhenti, pilih ulang mode Kamera) |

### B3. Mode Alat scanner (laptop, HID) — sekali scan

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Colok alat scanner ke laptop, buka `/scanner`, pilih mode "Alat scanner" | Halaman siap, tanpa pratinjau kamera |
| 2 | Scan QR **T2** | **Langsung** panel hijau "Serahkan N gelang" — tanpa tombol konfirmasi |
| 3 | Cek T2 di panel admin | "Sudah ditukar", petugas = akun yang login di laptop |
| 4 | Scan T2 lagi segera (atau alat mengirim dobel sendiri) | Panel netral "Baru saja Anda tukar pukul HH.MM" — bukan merah |
| 5 | Tunggu >2 menit, scan T2 lagi | Panel **merah** "Sudah ditukar" |
| 6 | Klik kotak "Cari manual", lalu scan QR dengan alat | Scan tetap terbaca sebagai scan (panel hasil muncul); kotak pencarian dikosongkan dan pencariannya tidak terkirim |
| 7 | Ketik pelan-pelan 48 karakter di kotak pencarian lalu Enter | Diperlakukan sebagai pencarian manual biasa, bukan scan |
| 8 | Scan kertas/QR rusak | "QR tidak dikenal" |

### B4. Pencarian manual — dua langkah

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Ketik `ab` di kotak pencarian, Enter | "Ketik minimal 3 karakter nama." Tidak ada hasil |
| 2 | Ketik kode **T3** apa adanya (`PJT26-XXXXXX`) | Kandidat tunggal tampil dengan nama + domisili |
| 3 | Ketik kode T3 dengan spasi dan huruf mirip: `pjt26 7ok3m9` (O→0, I/L→1) | Menemukan tiket yang sama |
| 4 | Ketik nama depan peserta T3 | Daftar kandidat (maks. 10 baris), diurutkan nama |
| 5 | Ketik nomor HP peserta T3 dalam format `0812…` | Kandidat yang sama ditemukan |
| 6 | Pilih kandidat T3 | Panel biru "Periksa data" + tombol "Serahkan N gelang" |
| 7 | Tekan "Serahkan N gelang" | Panel hijau. Status T3 di admin berubah "Sudah ditukar" |
| 8 | Cari nama yang tidak ada sama sekali | "QR tidak dikenal" |
| 9 | Cari peserta yang tiketnya **sudah dibatalkan** admin, lalu pilih | Panel **ungu** "Tiket dibatalkan" + "Arahkan peserta ke meja bantuan." Tanpa tombol konfirmasi; status tidak berubah |

### B5. Mode pesawat / jaringan putus

Ini yang paling sering terjadi di venue. Dijalankan di HP petugas.

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Buka `/scanner` dalam keadaan online, mode Kamera | Halaman siap |
| 2 | **Nyalakan mode pesawat**, lalu scan QR tiket yang belum ditukar | Setelah maks. 8 detik: panel **abu-abu** "Koneksi gagal. Silakan scan ulang." + bunyi gagal |
| 3 | Cek tiket itu lewat perangkat lain yang online | **Masih "Belum ditukar"** — tidak ada penukaran diam-diam |
| 4 | Masih mode pesawat, scan 3 QR lagi berturut-turut | Tiap scan tetap direspons dengan panel "Koneksi gagal" — halaman **tidak** membeku atau berhenti menerima scan |
| 5 | Matikan mode pesawat, tunggu sinyal kembali, scan QR yang sama | Berhasil normal, panel hijau. Penukaran tercatat sekali saja |
| 6 | Ulangi langkah 2–5 di mode **Alat scanner** | Perilaku sama: gagal koneksi tidak menukar apa pun, dan scan setelah online kembali berhasil |
| 7 | Muat ulang halaman `/scanner` dalam keadaan offline | Halaman tidak bisa dimuat (wajar). Setelah online, mode terakhir (Kamera/Alat scanner) masih terpilih |
| 8 | Setelah semua pulih, cocokkan `scan_logs` dengan yang benar-benar terjadi | Percobaan saat offline **tidak** meninggalkan baris `success` |

**Kesepakatan operasional yang diuji di sini:** kalau panel abu-abu "Koneksi gagal"
muncul, gelang **belum** diserahkan. Petugas harus scan ulang setelah sinyal kembali,
bukan menyerahkan gelang lalu menganggapnya tercatat.

### B6. Umpan balik & ergonomi

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Matikan suara lewat tombol di header, scan tiket | Tidak ada bunyi; getar tetap ada |
| 2 | Muat ulang halaman | Pilihan suara dan mode scan tetap seperti terakhir dipilih |
| 3 | Berdiri 1 meter dari layar HP, minta orang lain membaca panel hijau | Angka jumlah gelang terbaca dari jarak itu |
| 4 | Coba di bawah sinar matahari / lampu venue | Panel hijau/merah/abu/ungu masih bisa dibedakan |

---

## C. Alur admin

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Login admin, buka `/admin` | Dashboard: tiket terpakai, sisa, jumlah pendaftar, jumlah sudah check-in |
| 2 | Cocokkan angka dengan hasil uji di atas | Tiket terpakai = catatan awal + tiket uji yang dibuat. Sudah check-in = jumlah T1+T2+T3 |
| 3 | Buka `/admin/events`, ubah jadwal & venue | Tersimpan; halaman peserta langsung menampilkan nilai baru dengan format WIB (`j F Y, H.i`) |
| 4 | Isi `quota` lebih kecil dari tiket terpakai | Ditolak dengan pesan; angka tidak berubah |
| 5 | Buka `/admin/registrations` | Daftar peserta, 25 baris per halaman, terbaru di atas |
| 6 | Cari dengan nama, kode, dan nomor HP (`0812…`) | Ketiganya menemukan peserta yang sama |
| 7 | Buka detail satu peserta | Data lengkap + status notifikasi email & WhatsApp (`pending`/`sent`/`failed`) + jam penukaran + nama petugas |
| 8 | Tekan "Kirim ulang" | Status notifikasi kembali `pending`, lalu jadi `sent` dalam maks. 2 menit. Peserta menerima ulang |
| 9 | Batalkan satu registrasi uji yang **belum** ditukar | Berhasil: "Registrasi berhasil dibatalkan. Kuota sudah dikembalikan." |
| 10 | Cek dashboard | Tiket terpakai berkurang sesuai jumlah tiket registrasi itu |
| 11 | Buka `/tiket/{token}` milik registrasi yang dibatalkan | 404 |
| 12 | Cari registrasi yang dibatalkan lewat `/cari-tiket` | Pesan netral biasa, dan **tidak** ada e-ticket yang terkirim |
| 13 | Daftar ulang dengan email yang sama seperti registrasi yang dibatalkan | Diterima — email bebas dipakai lagi |
| 14 | Coba batalkan registrasi yang **sudah ditukar** | Ditolak dengan pesan yang menyebut tiket sudah ditukar. Status tidak berubah |
| 15 | Coba batalkan registrasi yang sudah dibatalkan | Ditolak dengan pesan "sudah dibatalkan sebelumnya" |
| 16 | Buka `/admin/users`, buat akun scanner baru | Berhasil; akun bisa login |
| 17 | Coba menonaktifkan akun admin Anda sendiri | Ditolak: "Anda tidak bisa menonaktifkan atau menurunkan role akun Anda sendiri." |
| 18 | Coba mengubah role satu-satunya admin aktif lain menjadi scanner | Ditolak: tidak boleh ada kondisi tanpa admin aktif |
| 19 | Buka `/admin/export` | CSV terunduh |
| 20 | Buka CSV di Excel | Huruf Indonesia (é, nama panjang) tampil benar (BOM UTF-8). Kolom: kode, nama, HP, jumlah tiket. Registrasi yang dibatalkan **tidak** ikut |
| 21 | Buat peserta uji bernama `=1+1` lalu export ulang | Di Excel selnya tampil sebagai teks `=1+1`, **tidak** dihitung sebagai formula |
| 22 | Logout, lalu buka `/admin` langsung | Dilempar ke `/login` |

---

## D. Uji beban ringan (opsional, kalau sempat)

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Minta 5–10 orang mendaftar bersamaan dari HP masing-masing | Semua dapat halaman sukses atau pesan kuota yang jelas — tidak ada 500 |
| 2 | Cek `events.tickets_taken` vs jumlah `ticket_qty` di `registrations` | Sama persis |
| 3 | Kalau ada yang dapat "Server sedang sibuk karena banyak pendaftar." | Wajar saat berebut kuota; tekan Daftar sekali lagi harus berhasil |
| 4 | 2 petugas scan 2 tiket berbeda bersamaan | Keduanya hijau, tidak saling mengunci |

Uji war kuota versi otomatis ada di `scripts/uji-war-kuota.php` (50 pendaftaran serentak
di database terpisah). Jalankan itu di lokal, bukan di produksi.

---

## E. Pembersihan setelah uji

Wajib, jangan dilewati.

- [ ] Batalkan **semua** registrasi bernama `UJI…` lewat `/admin/registrations`.
- [ ] Cek dashboard: tiket terpakai dan sisa kembali ke angka yang dicatat di awal.
- [ ] Nonaktifkan atau hapus akun `UJI-petugas-a` / `UJI-petugas-b` (pastikan masih ada
      minimal satu admin aktif).
- [ ] Kembalikan `quota`, `is_open`, `registration_open_at`, `registration_close_at`,
      jadwal, dan venue ke nilai sebenarnya.
- [ ] Kosongkan `failed_jobs` kalau ada sisa percobaan notifikasi yang gagal saat uji.
- [ ] Lanjut ke checklist H-1 di `docs/deploy.md`.
