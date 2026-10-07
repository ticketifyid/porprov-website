@extends('layouts.admin')

@section('title', 'Pengaturan Event')
@section('page_title', 'Pengaturan Event')
@section('breadcrumb')
    <li class="breadcrumb-item text-muted">Admin</li>
    <li class="breadcrumb-item"><span class="bullet bg-gray-400 w-5px h-2px"></span></li>
    <li class="breadcrumb-item text-muted">Pengaturan Event</li>
@endsection

@section('content')
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Informasi event</h3>
        </div>
        <div class="card-body">

            <form method="POST" action="{{ route('admin.events.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-5">
                    <label class="form-label">Lokasi (venue)</label>
                    <input type="text" name="venue" class="form-control" maxlength="200"
                           value="{{ old('venue', $event->venue) }}">
                    @error('venue') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Jadwal mulai acara (WIB)</label>
                    <input type="datetime-local" name="event_starts_at" class="form-control"
                           value="{{ old('event_starts_at', optional($event->event_starts_at)->format('Y-m-d\TH:i')) }}">
                    @error('event_starts_at') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Kuota tiket</label>
                    <input type="number" name="quota" class="form-control" min="0"
                           value="{{ old('quota', $event->quota) }}">
                    <div class="fs-7 text-muted mt-1">Terpakai saat ini: {{ $event->tickets_taken }} tiket. Kuota tidak bisa diturunkan di bawah angka ini.</div>
                    @error('quota') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Buka pendaftaran (WIB)</label>
                    <input type="datetime-local" name="registration_open_at" class="form-control"
                           value="{{ old('registration_open_at', optional($event->registration_open_at)->format('Y-m-d\TH:i')) }}">
                    @error('registration_open_at') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-5">
                    <label class="form-label">Tutup pendaftaran (WIB)</label>
                    <input type="datetime-local" name="registration_close_at" class="form-control"
                           value="{{ old('registration_close_at', optional($event->registration_close_at)->format('Y-m-d\TH:i')) }}">
                    @error('registration_close_at') <div class="text-danger fs-7 mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="form-check form-switch mb-5">
                    <input class="form-check-input" type="checkbox" name="is_open" value="1" id="is_open"
                           {{ old('is_open', $event->is_open) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_open">Pendaftaran dibuka (saklar manual)</label>
                </div>

                <button type="submit" class="btn btn-primary">Simpan</button>
            </form>
        </div>
    </div>
@endsection
