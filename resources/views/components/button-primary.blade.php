@props(['href' => null, 'type' => 'submit', 'disabled' => false])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn-primary']) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => 'btn-primary']) }}>{{ $slot }}</button>
@endif
