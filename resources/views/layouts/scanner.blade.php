<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <x-favicon />
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css">
    <link href="@versionedAsset('css/admin.css')" rel="stylesheet" type="text/css">
    <link href="@versionedAsset('css/scanner.css')" rel="stylesheet" type="text/css">
    <script>
        var defaultThemeMode = "system";
        var themeMode;
        if (document.documentElement) {
            if (document.documentElement.hasAttribute("data-bs-theme-mode")) {
                themeMode = document.documentElement.getAttribute("data-bs-theme-mode");
            } else {
                themeMode = localStorage.getItem("data-bs-theme") !== null
                    ? localStorage.getItem("data-bs-theme")
                    : defaultThemeMode;
            }
            if (themeMode === "system") {
                themeMode = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
            }
            document.documentElement.setAttribute("data-bs-theme", themeMode);
        }
    </script>
</head>
{{--
    Tampilan fokus tanpa sidebar (catatan Fase 7 docs/struktur.md): petugas
    memakai halaman ini dari HP di lapangan, hasil scan harus memakai seluruh
    lebar layar. Komponen dan tema mengikuti halaman admin (Metronic).
--}}
<body class="app-blank scanner-body">
    <header class="scanner-topbar bg-body border-bottom">
        <div class="scanner-topbar__inner d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <img src="{{ asset('ticketify-logo-512.png') }}" alt="Ticketify" class="scanner-topbar__logo">
                <div class="min-w-0">
                    <div class="fw-bold fs-6 text-gray-900 text-truncate">{{ auth()->user()->name }}</div>
                    @if (auth()->user()->name !== auth()->user()->role->label())
                        <div class="fs-8 text-gray-700">{{ auth()->user()->role->label() }}</div>
                    @endif
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <button type="button" id="sound-toggle" class="btn btn-sm btn-light-primary" aria-pressed="true">Suara: nyala</button>

                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-light">Dashboard</a>
                @endif

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-light-danger">Keluar</button>
                </form>
            </div>
        </div>
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
