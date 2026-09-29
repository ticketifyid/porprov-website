@props([
    'localName' => 'email_local',
    'domainName' => 'email_domain',
    'domainOtherName' => 'email_domain_other',
    'localValue' => '',
    'domainValue' => 'gmail.com',
    'domainOtherValue' => '',
])

@php
    $domains = ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'icloud.com'];
    $isCustomDomain = ! in_array($domainValue, $domains, true);
@endphp

<div class="field-email-group" data-email-field>
    <label for="{{ $localName }}">Email</label>

    <div class="email-compound">
        <input type="text" id="{{ $localName }}" name="{{ $localName }}" value="{{ $localValue }}"
               inputmode="email" autocomplete="email" autocapitalize="none" spellcheck="false"
               placeholder="nama.anda" data-email-local>
        <span class="at-sign" aria-hidden="true">@</span>
        <select id="{{ $domainName }}" name="{{ $domainName }}" aria-label="Domain email" data-email-domain>
            @foreach ($domains as $domain)
                <option value="{{ $domain }}" @selected($domainValue === $domain)>{{ $domain }}</option>
            @endforeach
            <option value="lainnya" @selected($isCustomDomain)>Lainnya&hellip;</option>
        </select>
    </div>

    <input type="text" id="{{ $domainOtherName }}" name="{{ $domainOtherName }}" class="domain-other"
           aria-label="Domain email lainnya" placeholder="contoh: kantor.co.id" autocapitalize="none"
           spellcheck="false" value="{{ $isCustomDomain ? $domainOtherValue : '' }}"
           data-email-domain-other @if (! $isCustomDomain) hidden @endif>

    <div class="email-preview">Email lengkap: <strong data-email-preview>&mdash;</strong></div>
    <div class="field__helper">Satu email hanya bisa mendaftar sekali.</div>

    @error($localName)
        <div class="field__error">{{ $message }}</div>
    @enderror
</div>
