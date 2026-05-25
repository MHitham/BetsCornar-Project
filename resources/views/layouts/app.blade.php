<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', \App\Models\Setting::get('clinic_name', 'عيادة بيطرية'))</title>

    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap"
        rel="stylesheet">

    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/layout.css'])
</head>

<body class="app-shell">
    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }
    </script>

    <div class="app-backdrop" aria-hidden="true">
        <span class="app-orb app-orb--one"></span>
        <span class="app-orb app-orb--two"></span>
        <span class="app-orb app-orb--three"></span>
    </div>

    
    <x-sidebar />

    
    <div id="main-wrapper">
        
        <x-navbar />

        
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

    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            if (window.innerWidth < 992) {
                document.getElementById('sidebar').classList.toggle('open');
                document.getElementById('sidebar-overlay').style.display =
                    document.getElementById('sidebar').classList.contains('open') ? 'block' : 'none';
            } else {
                document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', document.body.classList.contains('sidebar-collapsed'));
            }
        }

        function closeSidebar() {
            if (window.innerWidth < 992) {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('sidebar-overlay').style.display = 'none';
            }
        }
    </script>

    @stack('scripts')
    <script src="{{ asset('js/notifications.js') }}"></script>
</body>

</html>
