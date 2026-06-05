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

@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

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
                @php
                    $startTime = $appointment->start_time ? \Carbon\Carbon::parse($appointment->start_time) : null;
                    $endTime = $appointment->end_time ? \Carbon\Carbon::parse($appointment->end_time) : null;
                    $durationMinutes = ($startTime && $endTime) ? $endTime->diffInMinutes($startTime) : null;
                    $priceAmount = $appointment->payment->amount ?? $appointment->specialist->session_fee ?? null;
                    $priceCurrency = $appointment->payment->currency ?? $appointment->specialist->currency ?? null;
                @endphp
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
                            @if($startTime && $endTime)
                                {{ $startTime->format('H:i') }} - {{ $endTime->format('H:i') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'المدة' : 'Duration' }}</td>
                        <td>{{ $durationMinutes ? $durationMinutes . ' min' : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'السعر' : 'Price' }}</td>
                        <td class="fw-600">
                            {{ $priceAmount !== null ? number_format((float) $priceAmount, 2) . ' ' . ($priceCurrency ?? '') : '—' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'نوع الجلسة' : 'Session Type' }}</td>
                        <td>{{ $appointment->appointment_type ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone' }}</td>
                        <td>{{ $appointment->timezone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'رابط الاجتماع' : 'Meeting Link' }}</td>
                        <td>
                            @if($appointment->join_url)
                                <a href="{{ $appointment->join_url }}" target="_blank" rel="noopener noreferrer">{{ $appointment->join_url }}</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'إتاحة الرابط' : 'Link Available At' }}</td>
                        <td>
                            {{ $appointment->join_available_at ? $appointment->join_available_at->format('d M Y H:i') : '—' }}
                        </td>
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
                        @error('status')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-500">{{ $locale === 'ar' ? 'رابط الاجتماع' : 'Meeting Link' }}</label>
                        <input
                            type="url"
                            name="join_url"
                            class="form-control"
                            value="{{ old('join_url', $appointment->join_url) }}"
                            placeholder="https://meet.google.com/..."
                        >
                        <small class="text-muted d-block mt-1">
                            {{ $locale === 'ar' ? 'اختياري. اتركه فارغاً لحذف الرابط.' : 'Optional. Leave blank to remove the link.' }}
                        </small>
                        @error('join_url')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-500">{{ $locale === 'ar' ? 'إتاحة الرابط' : 'Link Available At' }}</label>
                        <input
                            type="datetime-local"
                            name="join_available_at"
                            class="form-control"
                            value="{{ old('join_available_at', $appointment->join_available_at?->format('Y-m-d\\TH:i')) }}"
                        >
                        @error('join_available_at')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-check me-1"></i>
                        {{ $locale === 'ar' ? 'حفظ الحالة والرابط' : 'Save Status & Link' }}
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
                @if($appointment->child->birth_date)
                    <small class="text-muted">
                        {{ \Carbon\Carbon::parse($appointment->child->birth_date)->age }}
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
