<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.app_name'))</title>

    {{-- Bootstrap 5 RTL --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">

    {{-- Google Fonts: Tajawal --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
        rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/layout.css'])
</head>

<body class="app-shell">

    <div class="app-backdrop" aria-hidden="true">
        <span class="app-orb app-orb--one"></span>
        <span class="app-orb app-orb--two"></span>
        <span class="app-orb app-orb--three"></span>
    </div>

    {{-- Sidebar --}}
    <x-sidebar />

    {{-- Main wrapper --}}
    <div id="main-wrapper">
        {{-- Top bar --}}
        <x-navbar />

        {{-- Page content --}}
        <main id="page-content">
            <div class="page-inner">
                <div class="alerts-stack">
                    <x-alerts />
                </div>

                <div class="page-stack">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>

    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebar-overlay').style.display =
                document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebar-overlay').style.display = 'none';
        }
    </script>

    @stack('scripts')
</body>

</html>
