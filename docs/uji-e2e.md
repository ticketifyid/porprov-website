# Uji manual end-to-end

Daftar uji yang dijalankan **sebelum hari H**, di lingkungan produksi (atau staging yang
persis sama), dengan perangkat yang benar-benar akan dipakai. Ditulis pada Fase 9
(`docs/prompts.md`); pasangannya `docs/deploy.md`.

Tes otomatis (`php artisan test`) sudah menutup aturan inti di level kode. Dokumen ini
menutup yang tidak bisa diuji otomatis: kamera HP, alat scanner fisik, HTTPS, jaringan
buruk, dan alur yang dijalankan orang sungguhan.

## Tanda di setiap langkah

- **[OTOMATIS]**: hasil yang diharapkan sudah dijamin tes di `tests/` (nama file
  disebutkan; `tests/Feature/` kecuali ditulis `Unit/`). Tetap boleh dilihat sekilas
  saat menjalankan langkah lain, tetapi tidak wajib diulang manual.
- **[MANUAL]**: hanya bisa dipastikan manual — perangkat nyata, kamera, alat scanner,
  jaringan, tampilan/JS di browser, email/WhatsApp sungguhan, atau belum ada tesnya.
  Catatan "logika: …" berarti sisi server sudah dites, tetapi yang dinilai di langkah
  itu adalah perilaku di perangkat/tampilan.

## Prioritas langkah [MANUAL] untuk hari H

Urut dari yang paling berisiko kalau gagal saat acara:

1. **B5 (semua) — jaringan putus saat scan.** Pastikan scan offline tidak menukar
   diam-diam, halaman tidak membeku, dan petugas paham "Koneksi gagal = gelang belum
   diserahkan".
2. **B2 1–3, 5, 7, 8, 10–12 — mode Kamera di HP petugas lewat HTTPS.** Izin kamera,
   pratinjau, konfirmasi, panel hijau/netral/merah/abu-abu, QR miring/jauh, layar dikunci.
3. **B3 1, 2, 4–8 — alat scanner fisik (HID) di laptop.** Sekali scan langsung hijau,
   kiriman dobel jadi panel netral, scan saat fokus di kotak pencarian.
4. **A3 5, 10 — QR terbaca dari layar dan dari screenshot** peserta.
5. **A3 3–4, A6 2, C8 — notifikasi sungguhan sampai** (email via SMTP, WhatsApp) dan
   status `pending` → `sent` lewat cron/queue di hosting.
6. **E (semua), A4 8, A5 4 — kembalikan kuota, `is_open`, jadwal, dan batalkan data
   `UJI`.** Lupa satu saja bisa menutup pendaftaran atau membuat angka salah di hari H.
7. **A1 1, B1 4 — redirect `http` → `https`** (kamera HP butuh HTTPS).
8. **D 1–4 — uji beban ringan** dengan orang sungguhan.
9. **A2 11–12 — Turnstile sungguhan** di domain produksi.
10. **B6 1–4 — umpan balik & ergonomi** (suara/getar, angka terbaca 1 meter, warna
    panel di bawah lampu venue).
11. **B1 5, B4 4, B4 6 — tampilan scanner & pencarian manual** (header, maks. 10
    kandidat urut nama, panel biru).
12. **C 1, 2, 5–7, 18, 20 — panel admin** (angka dashboard, paginasi, pencarian, detail,
    admin terakhir, CSV di Excel).
13. **A5 2–3 — halaman status "belum dibuka"** dan e-ticket lama saat tertutup.
14. **A2 1, 2, 4, 5, 8–10, A4 5 — UX form** (fokus, pesan per kolom, pecah email,
    stepper).
15. **A1 2, 5 — tampilan beranda** (tanpa scroll, landscape, laptop).

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

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Buka `http://DOMAIN` (sengaja `http`) | Berpindah otomatis ke `https://DOMAIN`, gembok muncul di address bar | [MANUAL] |
| 2 | Lihat halaman beranda | Nama acara, tanggal, dan venue sesuai yang diisi admin. Tombol "Daftar sekarang" terlihat tanpa perlu scroll | [MANUAL] tata letak; isi data: `RegistrationTest.php` |
| 3 | Lihat seluruh halaman | **Tidak ada** angka sisa kuota di mana pun | [OTOMATIS] `RegistrationTest.php` |
| 4 | Buka "View source" / Inspect | Tidak ada angka sisa kuota di HTML, juga tidak ada request API yang mengembalikannya | [OTOMATIS] `RegistrationTest.php` |
| 5 | Putar HP ke landscape, lalu buka di layar laptop | Tata letak tetap rapi, tidak ada scroll horizontal | [MANUAL] |

### A2. Form pendaftaran

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Tekan "Daftar sekarang" | Form terbuka, kolom Nama fokus/terlihat | [MANUAL] |
| 2 | Tekan Daftar dengan semua kolom kosong | Form kembali dengan pesan per kolom dalam Bahasa Indonesia ("Nama lengkap wajib diisi.", dst). Halaman **tidak** terlempar ke beranda | [MANUAL] teks & halaman tujuan; logika: `RegistrationTest.php` |
| 3 | Isi nama, pilih domisili dari daftar | Daftar berisi 35 kab/kota Jawa Tengah + "Luar Jawa Tengah" | [OTOMATIS] `RegencySeederTest.php` |
| 4 | Paste `Budi.Santoso+1@Gmail.com` ke kotak nama email | Otomatis terpecah: nama email `Budi.Santoso+1`, dropdown memilih `gmail.com` (bukan "Lainnya…"). Pratinjau "Email lengkap" tampil huruf kecil | [MANUAL] |
| 5 | Pilih "Lainnya…" lalu ketik `KANTOR.CO.ID` | Yang tersimpan di kotak menjadi huruf kecil `kantor.co.id` | [MANUAL] |
| 6 | Ketik domain `gmail.com` di kotak "Lainnya…" | Saat submit, diperlakukan sama dengan memilih `gmail.com` di dropdown | [OTOMATIS] `RegistrationTest.php` |
| 7 | Isi nomor HP `0812-3456 7890` | Diterima (normalisasi ke `62…` terjadi di server) | [OTOMATIS] `Unit/PhoneNormalizerTest.php`, `RegistrationTest.php` |
| 8 | Isi nomor HP `12345` | Ditolak: "Nomor WhatsApp tidak valid. Contoh: 081234567890." | [MANUAL] belum ada tesnya |
| 9 | Tekan tombol `−` saat jumlah tiket = 1 | Tombol `−` nonaktif, angka tetap 1 | [MANUAL] logika: `RegistrationTest.php` |
| 10 | Tekan `+` sampai mentok | Berhenti di 4. Tombol `+` nonaktif. Teks bantuan "Maks. 4 tiket." tetap tampil | [MANUAL] logika: `RegistrationTest.php` |
| 11 | Selesaikan Turnstile | Centang/verifikasi muncul dan selesai sendiri | [MANUAL] |
| 12 | Tekan Daftar tanpa menyelesaikan Turnstile (matikan dulu, atau submit cepat) | Ditolak: "Verifikasi keamanan belum selesai. Coba lagi." | [MANUAL] belum ada tesnya |
| 13 | Isi lengkap dan tekan Daftar | Halaman Sukses muncul | [OTOMATIS] `RegistrationTest.php` |

### A3. Halaman sukses & e-ticket

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Baca halaman sukses | Kode registrasi berformat `PJT26-XXXXXX` (6 karakter, tanpa huruf `I`, `L`, `O`, `U`). Jumlah tiket sesuai | [OTOMATIS] `RegistrationTest.php`, `Unit/RegistrationCodeGeneratorTest.php` |
| 2 | Lihat URL halaman sukses | Berisi token panjang acak, **bukan** kode registrasi dan bukan angka berurutan | [OTOMATIS] `RegistrationTest.php` |
| 3 | Tunggu maks. 2 menit, cek WhatsApp | Pesan e-ticket masuk berisi link `/tiket/{token}` | [MANUAL] |
| 4 | Cek email (termasuk folder spam) | E-ticket masuk | [MANUAL] isi email: `MailTicketNotifierTest.php` |
| 5 | Buka link e-ticket | Halaman tiket navy, QR tampil jelas dan terbaca di kecerahan layar sedang | [MANUAL] |
| 6 | Baca data di halaman tiket | Email tersamar (`bu***@gmail.com`), nomor tersamar (`0812-****-7890`). Nama, kode, domisili, dan jumlah tiket utuh | [OTOMATIS] `TicketPageTest.php` |
| 7 | Lihat badge status | "Belum ditukar" | [OTOMATIS] `TicketPageTest.php` |
| 8 | Ubah satu huruf token di URL | 404 | [OTOMATIS] `TicketPageTest.php` |
| 9 | Cek `view-source` halaman tiket | Ada `<meta name="robots" content="noindex, nofollow">`. Email dan nomor utuh **tidak** ada di HTML | [OTOMATIS] `TicketPageTest.php` |
| 10 | Screenshot halaman tiket | QR tetap terbaca saat di-scan dari screenshot (ini yang akan dilakukan peserta) | [MANUAL] |

### A4. Kuota & duplikat

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Daftar lagi dengan email yang sama persis | Ditolak di kolom email: "Email ini sudah dipakai untuk mendaftar. Gunakan menu Cari tiket saya…" | [OTOMATIS] `RegistrationTest.php` |
| 2 | Daftar lagi dengan `b.u.di+lain@googlemail.com` (kanonik sama dengan `budi@gmail.com`) | Ditolak dengan pesan yang sama — duplikat dicek lewat bentuk kanonik | [OTOMATIS] `RegistrationTest.php`, `Unit/EmailCanonicalizerTest.php` |
| 3 | Daftar dengan **nomor HP sama**, email berbeda | **Diterima** — satu nomor boleh dipakai beberapa pendaftaran (keluarga) | [OTOMATIS] `RegistrationTest.php` |
| 4 | Admin: turunkan kuota sampai sisa 2 (lewat `/admin/events`), lalu peserta daftar 4 tiket | Form kembali dengan banner: "Sisa kuota tinggal 2 tiket. Silakan kurangi jumlah tiket." diikuti "Data lain tidak perlu diisi ulang." Semua isian lain masih terisi | [OTOMATIS] `RegistrationTest.php`, `Admin/EventSettingsTest.php` |
| 5 | Di layar yang sama, tekan `+` pada stepper | Berhenti di 2, dan pesan "Sisa kuota tinggal 2 tiket." muncul di bawah stepper | [MANUAL] |
| 6 | Kurangi jadi 2 lalu Daftar | Berhasil | [OTOMATIS] `RegistrationTest.php` |
| 7 | Daftar lagi saat sisa 0 | Halaman Status: "Kuota pendaftaran sudah penuh" | [OTOMATIS] `RegistrationTest.php` |
| 8 | Kembalikan kuota ke angka semula lewat `/admin/events` | Form pendaftaran bisa dibuka lagi | [MANUAL] pemulihan data; logika simpan: `Admin/EventSettingsTest.php` |

### A5. Pendaftaran tertutup

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Admin matikan `is_open`, peserta buka `/` dan `/daftar` | Halaman Status "Pendaftaran sudah ditutup" (karena `registration_open_at` sudah lewat) | [OTOMATIS] `RegistrationTest.php` |
| 2 | Isi `registration_open_at` ke besok, matikan `is_open`, buka `/daftar` | Status "Pendaftaran belum dibuka" + tanggal buka. Tombol "Cari tiket saya" **tidak** tampil | [MANUAL] tanggal & tombol; teks status: `RegistrationTest.php` |
| 3 | Saat tertutup, buka `/tiket/{token}` lama | Tetap bisa dibuka — e-ticket lama tidak ikut mati | [MANUAL] belum ada tesnya |
| 4 | Kembalikan `is_open` ke semula | — | [MANUAL] pemulihan data |

### A6. Cari tiket

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Buka `/cari-tiket`, isi email terdaftar | Pesan netral: "Jika data terdaftar, link e-ticket sudah dikirim ulang ke email dan WhatsApp Anda. Cek juga folder spam." | [OTOMATIS] `TicketPageTest.php` |
| 2 | Cek WhatsApp/email | E-ticket masuk ulang | [MANUAL] |
| 3 | Isi email yang **tidak** terdaftar | Pesan **persis sama** dengan langkah 1 — tidak ada perbedaan kata, warna, atau waktu muncul yang membocorkan status | [OTOMATIS] `TicketPageTest.php` (HTML identik; kirim lewat queue) |
| 4 | Isi nomor HP terdaftar (format `0812…`) | Pesan netral yang sama; e-ticket untuk **semua** pendaftaran dengan nomor itu terkirim | [OTOMATIS] `TicketPageTest.php` |
| 5 | Lihat layar | Link e-ticket **tidak** pernah ditampilkan di halaman, hanya dikirim ke kontak | [OTOMATIS] `TicketPageTest.php` |
| 6 | Ulangi untuk kontak yang sama 5× berturut-turut | Pesan tetap netral dan sama setiap kali; mulai kiriman ke-4 tidak ada pesan baru yang masuk (batas 3/jam per kontak), **tanpa** halaman error | [OTOMATIS] `TicketPageTest.php` |

---

## B. Alur petugas (scanner)

Siapkan tiga registrasi uji yang belum ditukar: **T1** (untuk kamera), **T2** (untuk alat
scanner), **T3** (untuk pencarian manual). Tampilkan QR-nya di Perangkat 2.

### B1. Akses & HTTPS

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Buka `/scanner` tanpa login | Dilempar ke `/login` | [OTOMATIS] `AccessControlTest.php` |
| 2 | Login sebagai `UJI-petugas-a`, buka `/admin` | Ditolak (403) — scanner tidak boleh masuk panel admin | [OTOMATIS] `AccessControlTest.php` |
| 3 | Login sebagai admin, buka `/scanner` | Boleh — admin punya akses scanner | [OTOMATIS] `AccessControlTest.php` |
| 4 | Buka `/scanner` lewat `http://` | Berpindah ke `https://` | [MANUAL] |
| 5 | Lihat halaman scanner di HP | Tanpa sidebar. Header tipis berisi nama + role petugas, tombol suara, tombol Keluar. Area hasil besar | [MANUAL] tampilan; tanpa sidebar: `ScanTest.php` |
| 6 | Nonaktifkan `UJI-petugas-b` di panel admin, lalu coba login dengan akun itu | "Akun Anda sudah dinonaktifkan. Hubungi administrator." | [OTOMATIS] `AuthTest.php` |
| 7 | Salah password 6× | Mulai percobaan ke-6: "Terlalu banyak percobaan masuk. Silakan coba lagi dalam N detik." | [OTOMATIS] `AuthTest.php` |

### B2. Mode Kamera (HP, lewat HTTPS) — dua langkah

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Pilih mode "Kamera" | Browser meminta izin kamera | [MANUAL] |
| 2 | Izinkan | Pratinjau kamera tampil | [MANUAL] |
| 3 | Arahkan ke QR **T1** | Panel biru "Periksa data": nama, domisili, kode, jumlah gelang. Ada tombol "Serahkan N gelang" dan "Batal" | [MANUAL] |
| 4 | **Jangan tekan apa-apa**, cek status T1 di panel admin | Masih "Belum ditukar" — langkah pertama kamera tidak mengubah apa pun | [OTOMATIS] `ScanTest.php` |
| 5 | Tekan "Batal", lalu scan T1 lagi dan tekan "Serahkan N gelang" | Panel **hijau** besar: "Serahkan **N** gelang" + identitas peserta. Ada bunyi & getar | [MANUAL] logika: `ScanTest.php` |
| 6 | Cek T1 di panel admin | "Sudah ditukar", jam dan nama petugas terisi | [OTOMATIS] `ScanTest.php` |
| 7 | Scan T1 lagi dengan akun yang sama, dalam 2 menit | Panel **netral** (bukan merah): "Baru saja Anda tukar pukul HH.MM (N gelang)" | [MANUAL] logika: `ScanTest.php` |
| 8 | Login `UJI-petugas-b` di perangkat lain, scan T1 | Panel **merah**: "Sudah ditukar", pukul HH.MM, oleh nama petugas pertama | [MANUAL] logika: `ScanTest.php` |
| 9 | Cek T1 di panel admin | Jam penukaran **tidak berubah** — tetap penukaran pertama | [OTOMATIS] `ScanTest.php` |
| 10 | Scan QR acak / QR apa pun yang bukan tiket | Panel **abu-abu**: "QR tidak dikenal" + "Coba pencarian manual di bawah." | [MANUAL] logika: `ScanTest.php` |
| 11 | Scan sambil HP miring / QR agak jauh | Tetap terbaca, atau tidak bereaksi sama sekali — tidak boleh salah membaca jadi tiket lain | [MANUAL] |
| 12 | Kunci layar HP lalu buka lagi | Kamera bisa dilanjutkan (kalau berhenti, pilih ulang mode Kamera) | [MANUAL] |

### B3. Mode Alat scanner (laptop, HID) — sekali scan

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Colok alat scanner ke laptop, buka `/scanner`, pilih mode "Alat scanner" | Halaman siap, tanpa pratinjau kamera | [MANUAL] |
| 2 | Scan QR **T2** | **Langsung** panel hijau "Serahkan N gelang" — tanpa tombol konfirmasi | [MANUAL] logika: `ScanTest.php` |
| 3 | Cek T2 di panel admin | "Sudah ditukar", petugas = akun yang login di laptop | [OTOMATIS] `ScanTest.php` |
| 4 | Scan T2 lagi segera (atau alat mengirim dobel sendiri) | Panel netral "Baru saja Anda tukar pukul HH.MM" — bukan merah | [MANUAL] logika: `ScanTest.php` |
| 5 | Tunggu >2 menit, scan T2 lagi | Panel **merah** "Sudah ditukar" | [MANUAL] logika: `ScanTest.php` |
| 6 | Klik kotak "Cari manual", lalu scan QR dengan alat | Scan tetap terbaca sebagai scan (panel hasil muncul); kotak pencarian dikosongkan dan pencariannya tidak terkirim | [MANUAL] |
| 7 | Ketik pelan-pelan 48 karakter di kotak pencarian lalu Enter | Diperlakukan sebagai pencarian manual biasa, bukan scan | [MANUAL] |
| 8 | Scan kertas/QR rusak | "QR tidak dikenal" | [MANUAL] logika: `ScanTest.php` |

### B4. Pencarian manual — dua langkah

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Ketik `ab` di kotak pencarian, Enter | "Ketik minimal 3 karakter nama." Tidak ada hasil | [OTOMATIS] `ScanTest.php` |
| 2 | Ketik kode **T3** apa adanya (`PJT26-XXXXXX`) | Kandidat tunggal tampil dengan nama + domisili | [OTOMATIS] `ScanTest.php` |
| 3 | Ketik kode T3 dengan spasi dan huruf mirip: `pjt26 7ok3m9` (O→0, I/L→1) | Menemukan tiket yang sama | [OTOMATIS] `ScanTest.php`, `Unit/RegistrationCodeGeneratorTest.php` |
| 4 | Ketik nama depan peserta T3 | Daftar kandidat (maks. 10 baris), diurutkan nama | [MANUAL] batas 10 & urutan belum dites; pencarian nama: `ScanTest.php` |
| 5 | Ketik nomor HP peserta T3 dalam format `0812…` | Kandidat yang sama ditemukan | [OTOMATIS] `ScanTest.php` |
| 6 | Pilih kandidat T3 | Panel biru "Periksa data" + tombol "Serahkan N gelang" | [MANUAL] |
| 7 | Tekan "Serahkan N gelang" | Panel hijau. Status T3 di admin berubah "Sudah ditukar" | [OTOMATIS] `ScanTest.php` |
| 8 | Cari nama yang tidak ada sama sekali | "QR tidak dikenal" | [OTOMATIS] `ScanTest.php` |
| 9 | Cari peserta yang tiketnya **sudah dibatalkan** admin, lalu pilih | Panel **ungu** "Tiket dibatalkan" + "Arahkan peserta ke meja bantuan." Tanpa tombol konfirmasi; status tidak berubah | [OTOMATIS] `ScanTest.php` |

### B5. Mode pesawat / jaringan putus

Ini yang paling sering terjadi di venue. Dijalankan di HP petugas.

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Buka `/scanner` dalam keadaan online, mode Kamera | Halaman siap | [MANUAL] |
| 2 | **Nyalakan mode pesawat**, lalu scan QR tiket yang belum ditukar | Setelah maks. 8 detik: panel **abu-abu** "Koneksi gagal. Silakan scan ulang." + bunyi gagal | [MANUAL] |
| 3 | Cek tiket itu lewat perangkat lain yang online | **Masih "Belum ditukar"** — tidak ada penukaran diam-diam | [MANUAL] |
| 4 | Masih mode pesawat, scan 3 QR lagi berturut-turut | Tiap scan tetap direspons dengan panel "Koneksi gagal" — halaman **tidak** membeku atau berhenti menerima scan | [MANUAL] |
| 5 | Matikan mode pesawat, tunggu sinyal kembali, scan QR yang sama | Berhasil normal, panel hijau. Penukaran tercatat sekali saja | [MANUAL] |
| 6 | Ulangi langkah 2–5 di mode **Alat scanner** | Perilaku sama: gagal koneksi tidak menukar apa pun, dan scan setelah online kembali berhasil | [MANUAL] |
| 7 | Muat ulang halaman `/scanner` dalam keadaan offline | Halaman tidak bisa dimuat (wajar). Setelah online, mode terakhir (Kamera/Alat scanner) masih terpilih | [MANUAL] |
| 8 | Setelah semua pulih, cocokkan `scan_logs` dengan yang benar-benar terjadi | Percobaan saat offline **tidak** meninggalkan baris `success` | [MANUAL] |

**Kesepakatan operasional yang diuji di sini:** kalau panel abu-abu "Koneksi gagal"
muncul, gelang **belum** diserahkan. Petugas harus scan ulang setelah sinyal kembali,
bukan menyerahkan gelang lalu menganggapnya tercatat.

### B6. Umpan balik & ergonomi

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Matikan suara lewat tombol di header, scan tiket | Tidak ada bunyi; getar tetap ada | [MANUAL] |
| 2 | Muat ulang halaman | Pilihan suara dan mode scan tetap seperti terakhir dipilih | [MANUAL] |
| 3 | Berdiri 1 meter dari layar HP, minta orang lain membaca panel hijau | Angka jumlah gelang terbaca dari jarak itu | [MANUAL] |
| 4 | Coba di bawah sinar matahari / lampu venue | Panel hijau/merah/abu/ungu masih bisa dibedakan | [MANUAL] |

---

## C. Alur admin

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Login admin, buka `/admin` | Dashboard: tiket terpakai, sisa, jumlah pendaftar, jumlah sudah check-in | [MANUAL] login & redirect: `AuthTest.php`; isi dashboard belum dites |
| 2 | Cocokkan angka dengan hasil uji di atas | Tiket terpakai = catatan awal + tiket uji yang dibuat. Sudah check-in = jumlah T1+T2+T3 | [MANUAL] |
| 3 | Buka `/admin/events`, ubah jadwal & venue | Tersimpan; halaman peserta langsung menampilkan nilai baru dengan format WIB (`j F Y, H.i`) | [OTOMATIS] `Admin/EventSettingsTest.php`, `RegistrationTest.php`, `TicketPageTest.php` |
| 4 | Isi `quota` lebih kecil dari tiket terpakai | Ditolak dengan pesan; angka tidak berubah | [OTOMATIS] `Admin/EventSettingsTest.php` |
| 5 | Buka `/admin/registrations` | Daftar peserta, 25 baris per halaman, terbaru di atas | [MANUAL] paginasi & urutan belum dites |
| 6 | Cari dengan nama, kode, dan nomor HP (`0812…`) | Ketiganya menemukan peserta yang sama | [MANUAL] belum ada tesnya |
| 7 | Buka detail satu peserta | Data lengkap + status notifikasi email & WhatsApp (`pending`/`sent`/`failed`) + jam penukaran + nama petugas | [MANUAL] isi detail; halaman terbuka: `Admin/RegistrationAdminTest.php` |
| 8 | Tekan "Kirim ulang" | Status notifikasi kembali `pending`, lalu jadi `sent` dalam maks. 2 menit. Peserta menerima ulang | [MANUAL] cron & pengiriman; reset `pending` + dispatch: `Admin/RegistrationAdminTest.php`, `NotificationJobTest.php` |
| 9 | Batalkan satu registrasi uji yang **belum** ditukar | Berhasil: "Registrasi berhasil dibatalkan. Kuota sudah dikembalikan." | [OTOMATIS] `Admin/RegistrationAdminTest.php` |
| 10 | Cek dashboard | Tiket terpakai berkurang sesuai jumlah tiket registrasi itu | [OTOMATIS] `Admin/RegistrationAdminTest.php` |
| 11 | Buka `/tiket/{token}` milik registrasi yang dibatalkan | 404 | [OTOMATIS] `TicketPageTest.php` |
| 12 | Cari registrasi yang dibatalkan lewat `/cari-tiket` | Pesan netral biasa, dan **tidak** ada e-ticket yang terkirim | [OTOMATIS] `TicketPageTest.php`, `NotificationJobTest.php` |
| 13 | Daftar ulang dengan email yang sama seperti registrasi yang dibatalkan | Diterima — email bebas dipakai lagi | [OTOMATIS] `RegistrationTest.php` |
| 14 | Coba batalkan registrasi yang **sudah ditukar** | Ditolak dengan pesan yang menyebut tiket sudah ditukar. Status tidak berubah | [OTOMATIS] `Admin/RegistrationAdminTest.php` |
| 15 | Coba batalkan registrasi yang sudah dibatalkan | Ditolak dengan pesan "sudah dibatalkan sebelumnya" | [OTOMATIS] `Admin/RegistrationAdminTest.php` |
| 16 | Buka `/admin/users`, buat akun scanner baru | Berhasil; akun bisa login | [OTOMATIS] `Admin/UserManagementTest.php`, `AuthTest.php` |
| 17 | Coba menonaktifkan akun admin Anda sendiri | Ditolak: "Anda tidak bisa menonaktifkan atau menurunkan role akun Anda sendiri." | [OTOMATIS] `Admin/UserManagementTest.php` |
| 18 | Coba mengubah role satu-satunya admin aktif lain menjadi scanner | Ditolak: tidak boleh ada kondisi tanpa admin aktif | [MANUAL] penolakan "admin terakhir" belum dites |
| 19 | Buka `/admin/export` | CSV terunduh | [OTOMATIS] `Admin/ExportTest.php` |
| 20 | Buka CSV di Excel | Huruf Indonesia (é, nama panjang) tampil benar (BOM UTF-8). Kolom: kode, nama, HP, jumlah tiket. Registrasi yang dibatalkan **tidak** ikut | [MANUAL] tampilan Excel; BOM, kolom & tanpa yang dibatalkan: `Admin/ExportTest.php` |
| 21 | Buat peserta uji bernama `=1+1` lalu export ulang | Di Excel selnya tampil sebagai teks `=1+1`, **tidak** dihitung sebagai formula | [OTOMATIS] `Admin/ExportTest.php` |
| 22 | Logout, lalu buka `/admin` langsung | Dilempar ke `/login` | [OTOMATIS] `AuthTest.php`, `AccessControlTest.php` |

---

## D. Uji beban ringan (opsional, kalau sempat)

| # | Langkah | Hasil yang diharapkan | Uji |
|---|---|---|---|
| 1 | Minta 5–10 orang mendaftar bersamaan dari HP masing-masing | Semua dapat halaman sukses atau pesan kuota yang jelas — tidak ada 500 | [MANUAL] |
| 2 | Cek `events.tickets_taken` vs jumlah `ticket_qty` di `registrations` | Sama persis | [MANUAL] versi lokal: `scripts/uji-war-kuota.php` (di luar `tests/`) |
| 3 | Kalau ada yang dapat "Server sedang sibuk karena banyak pendaftar." | Wajar saat berebut kuota; tekan Daftar sekali lagi harus berhasil | [MANUAL] pesan & input tetap terisi: `RegistrationTest.php` |
| 4 | 2 petugas scan 2 tiket berbeda bersamaan | Keduanya hijau, tidak saling mengunci | [MANUAL] |

Uji war kuota versi otomatis ada di `scripts/uji-war-kuota.php` (50 pendaftaran serentak
di database terpisah). Jalankan itu di lokal, bukan di produksi.

---

## E. Pembersihan setelah uji

Wajib, jangan dilewati. Semua langkah di bagian ini **[MANUAL]**.

- [ ] [MANUAL] Batalkan **semua** registrasi bernama `UJI…` lewat `/admin/registrations`.
- [ ] [MANUAL] Cek dashboard: tiket terpakai dan sisa kembali ke angka yang dicatat di awal.
- [ ] [MANUAL] Nonaktifkan atau hapus akun `UJI-petugas-a` / `UJI-petugas-b` (pastikan
      masih ada minimal satu admin aktif).
- [ ] [MANUAL] Kembalikan `quota`, `is_open`, `registration_open_at`,
      `registration_close_at`, jadwal, dan venue ke nilai sebenarnya.
- [ ] [MANUAL] Kosongkan `failed_jobs` kalau ada sisa percobaan notifikasi yang gagal saat uji.
- [ ] [MANUAL] Lanjut ke checklist H-1 di `docs/deploy.md`.
