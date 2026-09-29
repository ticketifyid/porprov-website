@props(['name' => 'ticket_qty', 'max' => 4, 'value' => 1, 'hasError' => false])

@php $value = max(1, min($value, $max)); @endphp

<div class="stepper-group">
    <span id="{{ $name }}-label" class="stepper-group__label">Jumlah tiket</span>

    <div class="stepper-row">
        <div class="stepper" role="group" aria-labelledby="{{ $name }}-label"
             data-stepper="{{ $name }}" data-max="{{ $max }}" data-has-error="{{ $hasError ? 'true' : 'false' }}">
            <button type="button" class="stepper-btn" data-stepper-minus aria-label="Kurangi tiket">&minus;</button>
            <output class="stepper-output" data-stepper-output aria-live="polite">{{ $value }}</output>
            <button type="button" class="stepper-btn" data-stepper-plus aria-label="Tambah tiket">+</button>
            <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-stepper-input>
        </div>
        <div class="stepper-helper">1 tiket untuk 1 orang.<br>Maks. 4 tiket.</div>
    </div>

    <div class="stepper-quota-msg" role="status" data-stepper-quota-msg="{{ $name }}" hidden>
        Sisa kuota tinggal {{ $max }} tiket.
    </div>
</div>
