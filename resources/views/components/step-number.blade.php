@props(['number' => null, 'done' => false, 'variant' => 'default'])

<div {{ $attributes->merge(['class' => "step-number step-number--{$variant}"]) }}>
    @if ($done)
        <x-icon name="check" :size="18" :stroke="3" />
    @else
        {{ $number }}
    @endif
</div>
