@props([])

<div class="ticket-card">
    {{-- Mobile: satu kolom, sobekan horizontal --}}
    <div class="ticket-card__mobile">
        <div class="ticket-card__top">
            {{ $badge }}
            {{ $qr }}
            {{ $code }}
        </div>
        <x-ticket-perforation orientation="horizontal" />
        <div class="ticket-card__bottom">
            {{ $gelang }}
            {{ $details }}
        </div>
    </div>

    {{-- Desktop: tiga kolom, sobekan vertikal --}}
    <div class="ticket-card__desktop">
        <div class="ticket-card__col-qr">
            {{ $qr }}
            {{ $code }}
        </div>
        <x-ticket-perforation orientation="vertical" />
        <div class="ticket-card__col-details">
            <div class="ticket-card__toprow">
                {{ $badge }}
                @isset($hint)
                    <div class="ticket-card__hint">{{ $hint }}</div>
                @endisset
            </div>
            {{ $gelang }}
            {{ $details }}
        </div>
    </div>
</div>
