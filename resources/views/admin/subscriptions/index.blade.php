@extends('layouts.admin')

@section('title', 'Subscriptions')
@section('page_title', 'Subscriptions')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'الاشتراكات' : 'Subscriptions' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'الاشتراكات' : 'Subscriptions' }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-credit-card"></i>
            {{ $locale === 'ar' ? 'قائمة الاشتراكات' : 'Subscriptions List' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $subscriptions->total() }}
            </span>
        </h3>
        <form method="GET" action="{{ route('admin.subscriptions.index') }}">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="{{ $locale === 'ar' ? 'ابحث بالاسم أو الإيميل...' : 'Search by name or email...' }}"
                       value="{{ $search }}">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'المستخدم' : 'User' }}</th>
                    <th>{{ $locale === 'ar' ? 'الخطة' : 'Plan' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'تاريخ البدء' : 'Starts At' }}</th>
                    <th>{{ $locale === 'ar' ? 'تاريخ الانتهاء' : 'Ends At' }}</th>
                    <th>{{ $locale === 'ar' ? 'تحديث الحالة' : 'Update Status' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subscriptions as $sub)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $sub->id }}</td>
                    <td>
                        @if($sub->user)
                            <div class="d-flex align-items-center gap-2">
                                <div class="user-avatar-sm">
                                    {{ strtoupper(substr($sub->user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-600">{{ $sub->user->name }}</div>
                                    <small class="text-muted">{{ $sub->user->email }}</small>
                                </div>
                            </div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($sub->plan)
                            <div class="fw-600">{{ $sub->plan->name }}</div>
                            <small class="text-muted">
                                {{ number_format($sub->plan->price, 2) }} {{ $sub->plan->currency }}
                                / {{ ucfirst($sub->plan->billing_period) }}
                            </small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusMap = [
                                'active'   => 'status-badge active',
                                'trialing' => 'status-badge pending',
                                'canceled' => 'status-badge inactive',
                                'expired'  => 'status-badge inactive',
                            ];
                            $cls = $statusMap[$sub->status] ?? 'status-badge pending';
                        @endphp
                        <span class="{{ $cls }}">
                            <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                            {{ ucfirst($sub->status) }}
                        </span>
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ $sub->starts_at ? \Carbon\Carbon::parse($sub->starts_at)->format('d M Y') : '—' }}
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        @if($sub->ends_at)
                            @php $endsAt = \Carbon\Carbon::parse($sub->ends_at); @endphp
                            <span style="{{ $endsAt->isPast() ? 'color:#ef4444;' : '' }}">
                                {{ $endsAt->format('d M Y') }}
                            </span>
                        @else
                            <span class="text-muted">{{ $locale === 'ar' ? 'مدى الحياة' : 'Lifetime' }}</span>
                        @endif
                    </td>
                    <td>
                        <form action="{{ route('admin.subscriptions.status', $sub->id) }}" method="POST" class="d-flex gap-2 align-items-center">
                            @csrf
                            <select name="status" class="form-select form-select-sm" style="width:auto;">
                                @foreach(['active','trialing','canceled','expired'] as $s)
                                <option value="{{ $s }}" {{ $sub->status === $s ? 'selected' : '' }}>
                                    {{ ucfirst($s) }}
                                </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-primary" title="{{ $locale === 'ar' ? 'تحديث' : 'Update' }}">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-credit-card"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد اشتراكات' : 'No subscriptions found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($subscriptions->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $subscriptions->links() }}
    </div>
    @endif
</div>

@endsection
