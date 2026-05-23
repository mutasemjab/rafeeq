@extends('layouts.admin')

@section('title', 'Appointment Details')
@section('page_title', 'Appointment Details')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'تفاصيل الموعد' : 'Appointment Details' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.appointments.index') }}">{{ $locale === 'ar' ? 'المواعيد' : 'Appointments' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $appointment->booking_reference }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.appointments.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

<div class="row g-4">
    {{-- Booking Info --}}
    <div class="col-lg-8">
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-calendar-check"></i>
                    {{ $locale === 'ar' ? 'معلومات الحجز' : 'Booking Information' }}
                </h3>
                <span class="fw-600 font-monospace" style="color:#4f46e5;">
                    {{ $appointment->booking_reference }}
                </span>
            </div>
            <div class="p-4">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted fw-500" style="width:200px;">{{ $locale === 'ar' ? 'التاريخ' : 'Date' }}</td>
                        <td class="fw-600">
                            {{ isset($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date)->format('l, d F Y') : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'الوقت' : 'Time' }}</td>
                        <td class="fw-600">
                            {{ isset($appointment->scheduled_time) ? \Carbon\Carbon::parse($appointment->scheduled_time)->format('H:i') : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'المدة' : 'Duration' }}</td>
                        <td>{{ $appointment->duration_minutes ? $appointment->duration_minutes . ' min' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'السعر' : 'Price' }}</td>
                        <td class="fw-600">
                            {{ $appointment->amount ? number_format($appointment->amount, 2) . ' ' . ($appointment->currency ?? '') : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'نوع الجلسة' : 'Session Type' }}</td>
                        <td>{{ $appointment->session_type ?? '—' }}</td>
                    </tr>
                    @if($appointment->notes)
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'ملاحظات' : 'Notes' }}</td>
                        <td>{{ $appointment->notes }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- Review --}}
        @if($appointment->review)
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-star"></i>
                    {{ $locale === 'ar' ? 'التقييم' : 'Review' }}
                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="fas fa-star" style="color:{{ $i <= $appointment->review->rating ? '#f59e0b' : '#e2e8f0' }};"></i>
                    @endfor
                    <span class="fw-600">{{ $appointment->review->rating }}/5</span>
                </div>
                @if($appointment->review->comment)
                    <p class="text-muted mb-0">{{ $appointment->review->comment }}</p>
                @endif
                <small class="text-muted">{{ $appointment->review->created_at->format('d M Y') }}</small>
            </div>
        </div>
        @endif
    </div>

    {{-- Side Info --}}
    <div class="col-lg-4">
        {{-- Status Update --}}
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-arrows-rotate"></i>
                    {{ $locale === 'ar' ? 'تحديث الحالة' : 'Update Status' }}
                </h3>
            </div>
            <div class="p-4">
                @php
                    $statusColors = [
                        'pending_payment' => 'status-badge pending',
                        'confirmed'       => 'badge rounded-pill bg-info text-white',
                        'upcoming'        => 'badge rounded-pill bg-info text-white',
                        'completed'       => 'status-badge active',
                        'canceled'        => 'status-badge inactive',
                        'missed'          => 'status-badge inactive',
                    ];
                    $cls = $statusColors[$appointment->status] ?? 'status-badge pending';
                @endphp
                <div class="mb-3">
                    <span class="text-muted fw-500 me-2">{{ $locale === 'ar' ? 'الحالة الحالية:' : 'Current:' }}</span>
                    <span class="{{ $cls }}">{{ ucwords(str_replace('_', ' ', $appointment->status)) }}</span>
                </div>
                <form action="{{ route('admin.appointments.status', $appointment->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-500">{{ $locale === 'ar' ? 'الحالة الجديدة' : 'New Status' }}</label>
                        <select name="status" class="form-select">
                            @foreach(['pending_payment','confirmed','upcoming','completed','canceled','missed'] as $s)
                            <option value="{{ $s }}" {{ $appointment->status === $s ? 'selected' : '' }}>
                                {{ ucwords(str_replace('_', ' ', $s)) }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check me-1"></i>
                        {{ $locale === 'ar' ? 'تحديث الحالة' : 'Update Status' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- User Info --}}
        @if($appointment->user)
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user"></i>
                    {{ $locale === 'ar' ? 'المستخدم' : 'User' }}
                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="user-avatar-sm">
                        {{ strtoupper(substr($appointment->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-600">{{ $appointment->user->name }}</div>
                        <small class="text-muted">{{ $appointment->user->email }}</small>
                    </div>
                </div>
                @if($appointment->user->phone)
                    <small class="text-muted"><i class="fas fa-phone me-1"></i>{{ $appointment->user->phone }}</small>
                @endif
            </div>
        </div>
        @endif

        {{-- Specialist Info --}}
        @if($appointment->specialist)
        <div class="admin-card mb-4">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user-doctor"></i>
                    {{ $locale === 'ar' ? 'المتخصص' : 'Specialist' }}
                </h3>
            </div>
            <div class="p-4">
                <div class="d-flex align-items-center gap-3 mb-3">
                    @if($appointment->specialist->avatar)
                        <img src="{{ asset('storage/' . $appointment->specialist->avatar) }}"
                             style="width:44px; height:44px; border-radius:50%; object-fit:cover;">
                    @else
                        <div class="user-avatar-sm" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
                            {{ strtoupper(substr($appointment->specialist->name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div class="fw-600">{{ $appointment->specialist->name }}</div>
                        <small class="text-muted">{{ $appointment->specialist->title }}</small>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Child Info --}}
        @if($appointment->child)
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-child"></i>
                    {{ $locale === 'ar' ? 'الطفل' : 'Child' }}
                </h3>
            </div>
            <div class="p-4">
                <div class="fw-600">{{ $appointment->child->name }}</div>
                @if($appointment->child->date_of_birth)
                    <small class="text-muted">
                        {{ \Carbon\Carbon::parse($appointment->child->date_of_birth)->age }}
                        {{ $locale === 'ar' ? 'سنة' : 'yrs' }}
                    </small>
                @endif
                @if($appointment->child->diagnosis)
                    <div class="mt-1">
                        <small class="text-muted">{{ $appointment->child->diagnosis }}</small>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
