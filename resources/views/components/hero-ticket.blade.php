{{--
    Ilustrasi e-ticket pada hero beranda desktop (DesktopMain.dc.html).
    Kode dan QR di sini contoh statis dari artboard, bukan data registrasi.
--}}
<div class="hero-visual" aria-hidden="true">
    <div class="hero-ticket">
        <div class="hero-ticket__label">E-ticket Anda</div>
        <img src="{{ asset('img/hero-qr.svg') }}" width="150" height="150" alt="">
        <div class="hero-ticket__code">PJT26-7K3M9Q</div>
        <div class="hero-ticket__gelang">4 tiket = 4 gelang</div>
    </div>

    <x-strip-porprov class="strip-porprov--absolute strip-porprov--hero" />
</div>
