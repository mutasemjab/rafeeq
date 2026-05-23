<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('messages.dashboard')) — {{ __('messages.admin_panel') }}</title>

    {{-- Bootstrap 5 (RTL / LTR) --}}
    @if(app()->getLocale() === 'ar')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    @endif

    {{-- Font Awesome 6 --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Custom Admin CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/admin/css/style.css') }}">

    @yield('css')
</head>
<body class="{{ app()->getLocale() === 'ar' ? 'rtl' : '' }}">

<div class="admin-wrapper">

    {{-- Sidebar --}}
    @include('admin.includes.sidebar')

    {{-- Sidebar mobile overlay --}}
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    {{-- Main Content --}}
    <div class="main-content" id="mainContent">

        {{-- Top Navbar --}}
        @include('admin.includes.navbar')

        {{-- Page Content --}}
        <main class="page-content">
            @include('admin.includes.content')
        </main>

        {{-- Footer --}}
        @include('admin.includes.footer')

    </div>
</div>

{{-- Bootstrap 5 JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function () {
    const sidebar  = document.getElementById('adminSidebar');
    const main     = document.getElementById('mainContent');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggleDesktop = document.getElementById('sidebarToggleDesktop');
    const toggleMobile  = document.getElementById('sidebarToggleMobile');

    function closeMobileSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    }

    // Desktop: collapse / expand
    if (toggleDesktop) {
        toggleDesktop.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            main.classList.toggle('expanded');
        });
    }

    // Mobile: slide in / out
    if (toggleMobile) {
        toggleMobile.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileSidebar);
    }

    // Auto-close mobile sidebar on nav click
    document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth < 992) closeMobileSidebar();
        });
    });
})();
</script>

@yield('script')

</body>
</html>
