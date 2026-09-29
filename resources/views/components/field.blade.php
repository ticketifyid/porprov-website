@props(['label', 'name', 'type' => 'text', 'id' => null, 'helper' => null, 'span2' => false])

@php $id = $id ?? $name; @endphp

<div class="field {{ $span2 ? 'field--span-2' : '' }}">
    <label for="{{ $id }}">{{ $label }}</label>

    @if ($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
            {{ $slot }}
        </select>
    @else
        <input type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" {{ $attributes }}>
    @endif

    @if ($helper)
        <div class="field__helper">{{ $helper }}</div>
    @endif

    @error($name)
        <div class="field__error">{{ $message }}</div>
    @enderror
</div>
