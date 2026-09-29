@props([
    'variant' => 'back', // 'back' (mobile: tombol Kembali + logo), 'logo' (mobile: logo di tengah), 'none' (tanpa header mobile)
    'logoSize' => 96,
    'backHref' => '/',
    'homeHref' => '/',
    'linkLogo' => true,
])

@if ($variant !== 'none')
    <div class="header-mobile u-mobile-only {{ $variant === 'logo' ? 'header-mobile--center' : '' }}">
        @if ($variant === 'back')
            <a href="{{ $backHref }}" class="back-link">
                <x-icon name="chevron-left" :size="20" :stroke="2.2" />
                Kembali
            </a>
        @endif
        <x-logo :size="$logoSize" />
    </div>
@endif

<div class="header-desktop u-desktop-only">
    @if ($linkLogo)
        <a href="{{ $homeHref }}" class="header-desktop__logo-link">
            <x-logo :size="112" />
        </a>
    @else
        <x-logo :size="112" />
    @endif

    <div class="header-desktop__nav">
        {{ $slot }}
    </div>
</div>
