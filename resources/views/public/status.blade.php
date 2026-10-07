@extends('layouts.public')

@section('title', $title)

@section('content')
    <div class="page-shell centered-page">
        <x-header variant="none">
            @if ($showFind)
                <a href="{{ url('/cari-tiket') }}" class="nav-link">Cari tiket saya</a>
            @endif
        </x-header>

        <div class="centered-card-wrap--status">
            <div class="card card--status">
                <div class="icon-circle icon-circle--info">
                    <x-icon :name="$icon" :size="38" :stroke="2" />
                </div>

                <h1 class="h1-page">{{ $title }}</h1>
                <p class="card__body">{{ $body }}</p>

                @if ($showFind)
                    <x-button-secondary :href="url('/cari-tiket')" class="btn-secondary--status">
                        Sudah mendaftar? Cari tiket saya
                    </x-button-secondary>
                @endif
            </div>
        </div>

        <div class="site-footer site-footer--center">
            <span>Didukung oleh <strong>Ticketify</strong></span>
        </div>
    </div>
@endsection
