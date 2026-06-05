@extends('layouts.admin')

@section('title', 'Notifications')
@section('page_title', 'Notifications')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'الإشعارات' : 'Notifications' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'الإشعارات' : 'Notifications' }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-paper-plane"></i>
                    {{ $locale === 'ar' ? 'إرسال إشعار جديد' : 'Send New Notification' }}
                </h3>
            </div>
            <div class="admin-card-body">
                <form method="POST" action="{{ route('admin.notifications.store') }}" class="row g-3">
                    @csrf

                    <div class="col-md-4">
                        <label class="form-label">{{ $locale === 'ar' ? 'الجمهور' : 'Audience' }}</label>
                        <select name="audience" class="form-select">
                            <option value="all" {{ old('audience', 'all') === 'all' ? 'selected' : '' }}>
                                {{ $locale === 'ar' ? 'كل المستخدمين النشطين' : 'All Active Users' }}
                            </option>
                            <option value="user" {{ old('audience') === 'user' ? 'selected' : '' }}>
                                {{ $locale === 'ar' ? 'مستخدم محدد' : 'Specific User' }}
                            </option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">{{ $locale === 'ar' ? 'المستخدم المحدد' : 'Specific User' }}</label>
                        <select name="user_id" class="form-select">
                            <option value="">{{ $locale === 'ar' ? 'اختر مستخدماً' : 'Choose a user' }}</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ (string) old('user_id') === (string) $user->id ? 'selected' : '' }}>
                                {{ $user->name }} ({{ $user->email }})
                            </option>
                            @endforeach
                        </select>
                        <small class="text-muted">
                            {{ $locale === 'ar' ? 'يستخدم هذا الحقل فقط عند اختيار "مستخدم محدد".' : 'Used only when Audience is set to Specific User.' }}
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ $locale === 'ar' ? 'النوع' : 'Type' }}</label>
                        <input type="text" name="type" class="form-control" value="{{ old('type', 'admin_broadcast') }}" placeholder="admin_broadcast">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">{{ $locale === 'ar' ? 'العنوان' : 'Title' }}</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ $locale === 'ar' ? 'الرسالة' : 'Message' }}</label>
                        <textarea name="body" rows="4" class="form-control" required>{{ old('body') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ $locale === 'ar' ? 'بيانات إضافية JSON (اختياري)' : 'Extra JSON Data (Optional)' }}</label>
                        <textarea name="data_json" rows="6" class="form-control font-monospace" placeholder='{"screen":"appointments","appointment_id":"123"}'>{{ old('data_json', "{\n  \"screen\": \"appointments\"\n}") }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="send_push" value="1" id="send_push" {{ old('send_push', '1') ? 'checked' : '' }}>
                            <label class="form-check-label" for="send_push">
                                {{ $locale === 'ar' ? 'إرسال Push عبر Firebase أيضاً' : 'Also send a Firebase push notification' }}
                            </label>
                        </div>
                    </div>

                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-1"></i>
                            {{ $locale === 'ar' ? 'إرسال الإشعار' : 'Send Notification' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-bell"></i>
                    {{ $locale === 'ar' ? 'حالة Firebase' : 'Firebase Status' }}
                </h3>
            </div>
            <div class="admin-card-body">
                <div style="display:flex; flex-direction:column; gap:14px;">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;">{{ $locale === 'ar' ? 'المشروع' : 'Project' }}</span>
                        <span class="fw-600">{{ $firebaseProjectId ?: '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;">{{ $locale === 'ar' ? 'Push Tokens' : 'Push Tokens' }}</span>
                        <span class="fw-600">{{ $registeredDevicesCount }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="color:#64748b;">{{ $locale === 'ar' ? 'الإرسال من الخادم' : 'Server Sending' }}</span>
                        <span class="fw-600 {{ $firebaseConfigured ? 'text-success' : 'text-danger' }}">
                            {{ $firebaseConfigured ? ($locale === 'ar' ? 'جاهز' : 'Configured') : ($locale === 'ar' ? 'غير مهيأ' : 'Not Configured') }}
                        </span>
                    </div>
                </div>

                @if(! $firebaseConfigured)
                <div class="alert alert-warning mt-3 mb-0" style="font-size:0.85rem;">
                    {{ $locale === 'ar' ? 'لإرسال Push من لوحة التحكم، أضف FIREBASE_PROJECT_ID و FIREBASE_SERVICE_ACCOUNT_JSON في الخادم.' : 'To send push from the dashboard, add FIREBASE_PROJECT_ID and FIREBASE_SERVICE_ACCOUNT_JSON on the server.' }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-clock-rotate-left"></i>
            {{ $locale === 'ar' ? 'آخر الإشعارات' : 'Recent Notifications' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $recentNotifications->total() }}
            </span>
        </h3>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>{{ $locale === 'ar' ? 'المستخدم' : 'User' }}</th>
                    <th>{{ $locale === 'ar' ? 'النوع' : 'Type' }}</th>
                    <th>{{ $locale === 'ar' ? 'العنوان' : 'Title' }}</th>
                    <th>{{ $locale === 'ar' ? 'الرسالة' : 'Message' }}</th>
                    <th>{{ $locale === 'ar' ? 'القراءة' : 'Read' }}</th>
                    <th>{{ $locale === 'ar' ? 'الوقت' : 'Created' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentNotifications as $notification)
                <tr>
                    <td>
                        @if($notification->user)
                            <div class="fw-600">{{ $notification->user->name }}</div>
                            <small class="text-muted">{{ $notification->user->email }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><span class="badge rounded-pill bg-light text-dark">{{ $notification->type }}</span></td>
                    <td class="fw-600">{{ $notification->title }}</td>
                    <td style="max-width:320px; white-space:normal;">{{ $notification->body }}</td>
                    <td>
                        @if($notification->read_at)
                            <span class="badge rounded-pill bg-success-subtle text-success">{{ $locale === 'ar' ? 'مقروء' : 'Read' }}</span>
                        @else
                            <span class="badge rounded-pill bg-warning-subtle text-warning">{{ $locale === 'ar' ? 'غير مقروء' : 'Unread' }}</span>
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">{{ $notification->created_at?->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-bell"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد إشعارات بعد' : 'No notifications yet' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($recentNotifications->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $recentNotifications->appends(request()->query())->links() }}
    </div>
    @endif
</div>

@endsection
