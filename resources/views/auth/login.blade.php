@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <form class="form w-100" novalidate="novalidate" id="kt_sign_in_form" action="{{ route('login') }}" method="POST">
        @csrf

        <div class="text-center mb-11">
            <h1 class="text-gray-900 fw-bolder mb-3">Masuk</h1>
            <div class="text-gray-500 fw-semibold fs-6">Panel petugas &amp; administrator Ticketify</div>
        </div>

        @if (session('status'))
            <div class="alert alert-info">{{ session('status') }}</div>
        @endif

        <div class="fv-row mb-8">
            <label for="username" class="form-label fs-6 fw-bold text-gray-900">Username</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autofocus autocomplete="off"
                   class="form-control bg-transparent @error('username') is-invalid @enderror">
            @error('username')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="fv-row mb-3">
            <label for="password" class="form-label fs-6 fw-bold text-gray-900">Kata sandi</label>
            <input type="password" id="password" name="password" autocomplete="off"
                   class="form-control bg-transparent @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex flex-stack fs-base fw-semibold mb-8 mt-3">
            <div class="form-check form-check-custom form-check-solid">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
        </div>

        <div class="d-grid mb-10">
            <button type="submit" id="kt_sign_in_submit" class="btn btn-primary">
                <span class="indicator-label">Masuk</span>
                <span class="indicator-progress">Memproses...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </form>
@endsection
