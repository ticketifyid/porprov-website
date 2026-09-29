<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Scanner') - Ticketify</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css">
    <link href="@versionedAsset('css/scanner.css')" rel="stylesheet" type="text/css">
</head>
{{--
    Tampilan fokus tanpa sidebar (catatan Fase 7 docs/struktur.md): petugas
    memakai halaman ini dari HP di lapangan, hasil scan harus memakai seluruh
    lebar layar.
--}}
<body class="app-blank scanner-body">
    <header class="scanner-topbar">
        <span class="scanner-topbar__who">
            <span class="scanner-topbar__name">{{ auth()->user()->name }}</span>
            <span class="scanner-topbar__role">{{ auth()->user()->role->label() }}</span>
        </span>

        <span class="scanner-topbar__actions">
            <button type="button" id="sound-toggle" class="scanner-topbar__btn" aria-pressed="true">Suara: nyala</button>

            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dashboard') }}" class="scanner-topbar__btn">Dashboard</a>
            @endif

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="scanner-topbar__btn">Keluar</button>
            </form>
        </span>
    </header>

    <main class="scanner-main">
        @yield('content')
    </main>

    {{--
        Urutan penting: modul mode dimuat lebih dulu supaya listener
        scanner:mode / scanner:reset sudah terpasang saat scanner.js
        menjalankan init() dan menerapkan mode terakhir dari localStorage.
    --}}
    <script src="@versionedAsset('js/vendor/html5-qrcode.min.js')"></script>
    <script src="@versionedAsset('js/scanner-camera.js')"></script>
    <script src="@versionedAsset('js/scanner-hardware.js')"></script>
    <script src="@versionedAsset('js/scanner.js')"></script>
</body>
</html>
