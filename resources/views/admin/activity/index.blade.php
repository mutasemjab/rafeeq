@extends('layouts.admin')

@section('title', 'Activity Log')
@section('page_title', 'Activity Log')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'سجل النشاط' : 'Activity Log' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'سجل النشاط' : 'Activity Log' }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-clock-rotate-left"></i>
            {{ $locale === 'ar' ? 'سجل نشاط المسؤولين' : 'Admin Activity Log' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $logs->total() }}
            </span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ $locale === 'ar' ? 'المسؤول' : 'Admin' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراء' : 'Action' }}</th>
                    <th>{{ $locale === 'ar' ? 'نوع الكيان' : 'Entity Type' }}</th>
                    <th>{{ $locale === 'ar' ? 'معرف الكيان' : 'Entity ID' }}</th>
                    <th>{{ $locale === 'ar' ? 'القيم القديمة' : 'Old Values' }}</th>
                    <th>{{ $locale === 'ar' ? 'القيم الجديدة' : 'New Values' }}</th>
                    <th>{{ $locale === 'ar' ? 'التاريخ' : 'Date' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
                                {{ strtoupper(substr($log->admin_name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-600">{{ $log->admin_name }}</div>
                                <small class="text-muted">{{ $log->admin_email }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @php
                            $actionColors = [
                                'create' => 'background:#ecfdf5; color:#10b981;',
                                'update' => 'background:#eff6ff; color:#3b82f6;',
                                'delete' => 'background:#fef2f2; color:#ef4444;',
                                'restore'=> 'background:#fffbeb; color:#f59e0b;',
                                'login'  => 'background:#f1f5f9; color:#64748b;',
                            ];
                            $action = strtolower($log->action ?? '');
                            $actionStyle = $actionColors[$action] ?? 'background:#f1f5f9; color:#64748b;';
                        @endphp
                        <span class="badge rounded-pill" style="{{ $actionStyle }} font-size:0.78rem; padding:4px 10px;">
                            {{ ucfirst($log->action ?? '—') }}
                        </span>
                    </td>
                    <td>
                        @if(isset($log->entity_type))
                            <span class="badge rounded-pill bg-light text-dark">
                                {{ class_basename($log->entity_type) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="color:#94a3b8; font-size:0.80rem;">
                        {{ $log->entity_id ?? '—' }}
                    </td>
                    <td style="max-width:180px;">
                        @if(isset($log->old_values) && $log->old_values)
                            <code class="small" style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#64748b; max-width:180px;"
                                  title="{{ $log->old_values }}">
                                {{ Str::limit($log->old_values, 60) }}
                            </code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="max-width:180px;">
                        @if(isset($log->new_values) && $log->new_values)
                            <code class="small" style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#64748b; max-width:180px;"
                                  title="{{ $log->new_values }}">
                                {{ Str::limit($log->new_values, 60) }}
                            </code>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem; white-space:nowrap;">
                        {{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, H:i') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-clock-rotate-left"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد سجلات نشاط' : 'No activity logs found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $logs->links() }}
    </div>
    @endif
</div>

@endsection
