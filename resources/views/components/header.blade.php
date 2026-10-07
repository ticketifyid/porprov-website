@props([
    'variant' => 'back', // 'back' (mobile: tombol Kembali), 'none' (tanpa header mobile; logo ada di komponen logo-bar pada layout)
    'backHref' => '/',
])

@if ($variant === 'back')
    <div class="header-mobile u-mobile-only">
        <a href="{{ $backHref }}" class="back-link">
            <x-icon name="chevron-left" :size="20" :stroke="2.2" />
            Kembali
        </a>
    </div>
@endif

<div class="header-desktop u-desktop-only">
    <div class="header-desktop__nav">
        {{ $slot }}
    </div>
</div>
