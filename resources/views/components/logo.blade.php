@props(['size' => 96])

<img src="{{ asset('img/logo-porprov.png') }}" alt="Logo Porprov Jawa Tengah XVII 2026"
     {{ $attributes->merge(['style' => "width: {$size}px"]) }}>
