@props(['variant' => 'warn', 'icon' => null])

@php $icon = $icon ?? ($variant === 'warn' ? 'warning' : 'envelope'); @endphp

<div {{ $attributes->merge(['class' => "alert alert-{$variant}", 'role' => 'status']) }}>
    <x-icon :name="$icon" :size="20" :stroke="2" class="alert__icon" />
    <div class="alert__text">{{ $slot }}</div>
</div>
