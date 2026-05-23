@extends('layouts.admin')

@section('title', 'Appointments')
@section('page_title', 'Appointments')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'المواعيد' : 'Appointments' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'المواعيد' : 'Appointments' }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-calendar-check"></i>
            {{ $locale === 'ar' ? 'قائمة المواعيد' : 'Appointments List' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $appointments->total() }}
            </span>
        </h3>
        <div class="d-flex gap-2">
            <form method="GET" action="{{ route('admin.appointments.index') }}" class="d-flex gap-2">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="{{ $locale === 'ar' ? 'ابحث برقم الحجز...' : 'Search by reference...' }}"
                           value="{{ $search }}">
                </div>
                <select name="status" class="form-select" style="min-width:150px;" onchange="this.form.submit()">
                    <option value="">{{ $locale === 'ar' ? 'كل الحالات' : 'All Statuses' }}</option>
                    @foreach(['pending_payment','confirmed','upcoming','completed','canceled','missed'] as $s)
                    <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>
                        {{ ucwords(str_replace('_', ' ', $s)) }}
                    </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ $locale === 'ar' ? 'رقم الحجز' : 'Reference' }}</th>
                    <th>{{ $locale === 'ar' ? 'المستخدم' : 'User' }}</th>
                    <th>{{ $locale === 'ar' ? 'المتخصص' : 'Specialist' }}</th>
                    <th>{{ $locale === 'ar' ? 'التاريخ' : 'Date' }}</th>
                    <th>{{ $locale === 'ar' ? 'الوقت' : 'Time' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($appointments as $appointment)
                <tr>
                    <td>
                        <span class="fw-600 font-monospace" style="color:#4f46e5; font-size:0.85rem;">
                            {{ $appointment->booking_reference }}
                        </span>
                    </td>
                    <td>
                        @if($appointment->user)
                            <div class="fw-600">{{ $appointment->user->name }}</div>
                            <small class="text-muted">{{ $appointment->user->email }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($appointment->specialist)
                            <div class="fw-600">{{ $appointment->specialist->name }}</div>
                            <small class="text-muted">{{ $appointment->specialist->title }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ isset($appointment->scheduled_date) ? \Carbon\Carbon::parse($appointment->scheduled_date)->format('d M Y') : '—' }}
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ isset($appointment->scheduled_time) ? \Carbon\Carbon::parse($appointment->scheduled_time)->format('H:i') : '—' }}
                    </td>
                    <td>
                        @php
                            $statusClasses = [
                                'pending_payment' => 'status-badge pending',
                                'confirmed'       => 'badge rounded-pill bg-info text-white',
                                'upcoming'        => 'badge rounded-pill bg-info text-white',
                                'completed'       => 'status-badge active',
                                'canceled'        => 'status-badge inactive',
                                'missed'          => 'status-badge inactive',
                            ];
                            $cls = $statusClasses[$appointment->status] ?? 'status-badge pending';
                        @endphp
                        <span class="{{ $cls }}">
                            {{ ucwords(str_replace('_', ' ', $appointment->status)) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.appointments.show', $appointment->id) }}"
                           class="btn-action edit"
                           title="{{ $locale === 'ar' ? 'عرض' : 'View' }}">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد مواعيد' : 'No appointments found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($appointments->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $appointments->appends(request()->query())->links() }}
    </div>
    @endif
</div>

@endsection
