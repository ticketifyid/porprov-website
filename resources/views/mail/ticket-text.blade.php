{{-- Versi teks polos: {!! !!} supaya karakter seperti & tidak menjadi &amp;. Bukan HTML, jadi tidak ada yang dirender. --}}
Halo {!! $name !!},

Pendaftaran Anda berhasil. Berikut e-ticket Opening Ceremony Porprov Jateng XVII 2026.

Kode registrasi: {!! $code !!}
Jumlah tiket: {{ $ticketQty }} tiket, tukar dengan {{ $ticketQty }} gelang
@if ($eventStartsAt)
Tanggal: {!! $eventStartsAt !!} WIB
@endif
@if ($venue)
Lokasi: {!! $venue !!}
@endif

Lihat e-ticket:
{!! $ticketUrl !!}

Tunjukkan QR pada e-ticket kepada petugas saat registrasi ulang untuk ditukar dengan gelang. Satu QR berlaku untuk semua gelang Anda sekaligus.

Jangan bagikan link e-ticket ini kepada orang lain.
