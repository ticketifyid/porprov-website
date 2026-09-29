@props(['name', 'size' => 22, 'stroke' => 2])

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     {{ $attributes }}>
    @switch($name)
        @case('calendar')
            <rect x="3" y="4" width="18" height="18" rx="2"></rect>
            <path d="M16 2v4M8 2v4M3 10h18"></path>
            @break

        @case('location')
            <path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"></path>
            <circle cx="12" cy="10" r="2.5"></circle>
            @break

        @case('ticket')
            <path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"></path>
            <path d="M13 6v12" stroke-dasharray="2 2"></path>
            @break

        @case('check')
            <path d="M5 12l5 5L20 7"></path>
            @break

        @case('chevron-left')
            <path d="M15 18l-6-6 6-6"></path>
            @break

        @case('clock')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7v5l3 2"></path>
            @break

        @case('lock')
            <rect x="5" y="11" width="14" height="10" rx="2"></rect>
            <path d="M8 11V7a4 4 0 0 1 8 0v4"></path>
            @break

        @case('crowd')
            <circle cx="9" cy="8" r="3"></circle>
            <path d="M3 20a6 6 0 0 1 12 0"></path>
            <circle cx="17" cy="9" r="2.5"></circle>
            <path d="M15.5 14.5A5 5 0 0 1 21 20"></path>
            @break

        @case('warning')
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 8v5"></path>
            <path d="M12 16h.01"></path>
            @break

        @case('envelope')
            <path d="M4 4h16v16H4z"></path>
            <path d="M4 6l8 7 8-7"></path>
            @break

        @case('sun')
            <circle cx="12" cy="12" r="4"></circle>
            <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"></path>
            @break

        @case('device')
            <rect x="5" y="2" width="14" height="20" rx="2"></rect>
            <path d="M11 18h2"></path>
            @break

        @case('wristband')
            <rect x="3" y="9" width="18" height="6" rx="3"></rect>
            <path d="M8 9v6M16 9v6"></path>
            @break
    @endswitch
</svg>
