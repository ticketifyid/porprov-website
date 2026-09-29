@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <h3 class="mb-3">Selamat datang, {{ auth()->user()->name }}.</h3>
            <p class="text-muted mb-0">
                Ringkasan kuota, pendaftar, dan check-in akan ditampilkan di sini pada fase berikutnya.
            </p>
        </div>
    </div>
@endsection
