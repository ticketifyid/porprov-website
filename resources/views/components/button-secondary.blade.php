@props(['href' => null, 'type' => 'button', 'disabled' => false])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-secondary']) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => 'btn-secondary']) }}>{{ $slot }}</button>
@endif
