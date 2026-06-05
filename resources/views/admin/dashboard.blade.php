@extends('layouts.admin')

@section('title', __('messages.dashboard'))
@section('page_title', __('messages.dashboard'))

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ __('messages.dashboard') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">{{ __('messages.dashboard') }}</li>
            </ol>
        </nav>
    </div>
</div>

{{-- Stat Cards --}}
<div class="row g-4 mb-4">

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-primary-soft">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">{{ $usersCount ?? 0 }}</div>
                <div class="stat-label">{{ __('messages.users') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <a href="{{ route('admin.appointments.index') }}" class="stat-card text-decoration-none h-100" style="display:block;">
            <div class="stat-icon-wrap bg-success-soft">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">{{ $appointmentsCount ?? 0 }}</div>
                <div class="stat-label">{{ $locale === 'ar' ? 'المواعيد' : 'Appointments' }}</div>
                <div style="font-size:0.75rem; color:#2563eb; margin-top:6px;">
                    {{ $locale === 'ar' ? 'فتح المواعيد' : 'Open Appointments' }}
                </div>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <a href="{{ route('admin.notifications.index') }}" class="stat-card text-decoration-none h-100" style="display:block;">
            <div class="stat-icon-wrap bg-warning-soft">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">{{ $notificationsCount ?? 0 }}</div>
                <div class="stat-label">{{ $locale === 'ar' ? 'الإشعارات' : 'Notifications' }}</div>
                <div style="font-size:0.75rem; color:#2563eb; margin-top:6px;">
                    {{ $locale === 'ar' ? 'إرسال إشعار' : 'Send Notification' }}
                </div>
            </div>
        </a>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon-wrap bg-info-soft">
                <i class="fas fa-mobile-screen-button"></i>
            </div>
            <div class="stat-info">
                <div class="stat-number">{{ $pushDevicesCount ?? 0 }}</div>
                <div class="stat-label">{{ $locale === 'ar' ? 'أجهزة Push' : 'Push Devices' }}</div>
            </div>
        </div>
    </div>

</div>

{{-- Recent Users --}}
<div class="row g-4">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-users"></i>
                    {{ __('messages.recent_users') }}
                </h3>
                <a href="{{ route('admin.users.index') }}" class="btn btn-sm btn-outline-primary">
                    {{ __('messages.view_all') }}
                </a>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.user_name') }}</th>
                            <th>{{ __('messages.email') }}</th>
                            <th>{{ __('messages.phone') }}</th>
                            <th>{{ __('messages.created_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers ?? [] as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="user-avatar-sm">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <span class="fw-600">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?? '—' }}</td>
                            <td>{{ $user->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>{{ __('messages.no_users_yet') }}</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-circle-info"></i>
                    {{ $locale === 'ar' ? 'معلومات النظام' : 'System Info' }}
                </h3>
            </div>
            <div class="admin-card-body">
                <ul class="list-unstyled" style="display:flex; flex-direction:column; gap:14px;">
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-code-branch me-2 text-primary"></i>
                            {{ $locale === 'ar' ? 'إطار العمل' : 'Framework' }}
                        </span>
                        <span class="fw-600">Laravel 9</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-calendar me-2 text-success"></i>
                            {{ $locale === 'ar' ? 'التاريخ' : 'Date' }}
                        </span>
                        <span class="fw-600">{{ now()->format('d M Y') }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-globe me-2 text-info"></i>
                            {{ $locale === 'ar' ? 'اللغة' : 'Language' }}
                        </span>
                        <span class="fw-600">{{ $locale === 'ar' ? 'العربية' : 'English' }}</span>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b; font-size:0.85rem;">
                            <i class="fas fa-user-shield me-2 text-warning"></i>
                            {{ $locale === 'ar' ? 'المدير' : 'Admin' }}
                        </span>
                        <span class="fw-600">{{ auth()->user()->name ?? auth()->user()->username }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@endsection
