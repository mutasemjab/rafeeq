@php $locale = app()->getLocale(); @endphp

<header class="top-navbar">

    {{-- Desktop sidebar toggle --}}
    <button class="navbar-toggle-btn d-none d-lg-flex" id="sidebarToggleDesktop" title="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Mobile sidebar toggle --}}
    <button class="navbar-toggle-btn d-flex d-lg-none" id="sidebarToggleMobile" title="Open menu">
        <i class="fas fa-bars"></i>
    </button>

    {{-- Page title slot --}}
    <span class="navbar-page-title d-none d-sm-block">@yield('page_title', __('messages.dashboard'))</span>

    <div class="navbar-spacer"></div>

    <div class="navbar-actions">

        {{-- Language switcher --}}
        @if($locale === 'ar')
            <a href="{{ LaravelLocalization::getLocalizedURL('en') }}"
               class="navbar-btn lang-btn"
               title="Switch to English">
                <i class="fas fa-language"></i> EN
            </a>
        @else
            <a href="{{ LaravelLocalization::getLocalizedURL('ar') }}"
               class="navbar-btn lang-btn"
               title="التبديل إلى العربية">
                <i class="fas fa-language"></i> AR
            </a>
        @endif

        {{-- User dropdown --}}
        <div class="dropdown">
            <a href="#" class="user-dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth()->user()->name ?? auth()->user()->username ?? 'A', 0, 1)) }}
                </div>
                <div class="d-none d-md-block text-start">
                    <div class="user-name">{{ auth()->user()->name ?? auth()->user()->username }}</div>
                    <div class="user-role">{{ $locale === 'ar' ? 'مدير النظام' : 'Administrator' }}</div>
                </div>
                <i class="fas fa-chevron-down" style="font-size:0.65rem; color:#94a3b8;"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="{{ route('admin.login.edit', auth()->user()->id) }}">
                        <i class="fas fa-user-gear"></i>
                        {{ $locale === 'ar' ? 'إعدادات الحساب' : 'Account Settings' }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="#"
                       onclick="event.preventDefault(); document.getElementById('navbar-logout-form').submit()">
                        <i class="fas fa-right-from-bracket"></i>
                        {{ $locale === 'ar' ? 'تسجيل الخروج' : 'Logout' }}
                    </a>
                </li>
            </ul>
        </div>

    </div>

    {{-- Hidden logout form --}}
    <form id="navbar-logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
        @csrf
    </form>

</header>
