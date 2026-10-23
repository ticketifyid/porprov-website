<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title>{{ config('app.name') }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <x-favicon />
    <link href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css">
    <link href="@versionedAsset('css/admin.css')" rel="stylesheet" type="text/css">
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
<body id="kt_body" class="app-blank bgi-size-cover bgi-position-center bgi-no-repeat">
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">

            <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
                <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                    <div class="w-lg-500px p-10">
                        @yield('content')
                    </div>
                </div>
            </div>

            {{-- Panel brand: navy yang sama dengan halaman peserta + strip empat warna Porprov di dasar. --}}
            <div class="auth-brand d-flex flex-lg-row-fluid w-lg-50 order-1 order-lg-2">
                <div class="d-flex flex-column flex-center py-7 py-lg-15 px-5 px-md-15 w-100">
                    <img src="{{ asset('ticketify-logo-512.png') }}" alt="Ticketify" class="auth-brand__logo mb-5">
                    <p class="auth-brand__tagline fs-4 text-center m-0">Registrasi &amp; e-tiket Opening Ceremony Porprov Jateng XVII 2026</p>
                </div>
                <div class="auth-brand__strip" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            </div>
        </div>
    </div>

    <script src="{{ asset('metronic/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('metronic/js/scripts.bundle.js') }}"></script>
    @stack('scripts')
</body>
</html>
