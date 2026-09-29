@props(['variant' => 'info', 'icon' => null])

@php
    $icon = $icon ?? ($variant === 'success' ? 'check' : 'clock');
    $stroke = $variant === 'success' ? 3 : 2.5;
@endphp

<span {{ $attributes->merge(['class' => "badge badge-{$variant}"]) }}>
    <x-icon :name="$icon" :size="16" :stroke="$stroke" />
    {{ $slot }}
</span>
