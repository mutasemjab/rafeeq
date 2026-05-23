@extends('layouts.admin')

@section('title', 'Child Profile')
@section('page_title', 'Child Profile')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $child->name }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.children.index') }}">{{ $locale === 'ar' ? 'الأطفال' : 'Children' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $child->name }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.children.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

{{-- Profile Card --}}
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-child"></i>
                    {{ $locale === 'ar' ? 'معلومات الطفل' : 'Child Profile' }}
                </h3>
            </div>
            <div class="p-4 text-center">
                <div style="width:80px; height:80px; border-radius:50%; background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; font-size:2rem; font-weight:700; display:flex; align-items:center; justify-content:center; margin:0 auto 16px;">
                    {{ strtoupper(substr($child->name, 0, 1)) }}
                </div>
                <h4 class="fw-bold mb-1">{{ $child->name }}</h4>
                @if($child->date_of_birth)
                    <p class="text-muted mb-0">
                        {{ \Carbon\Carbon::parse($child->date_of_birth)->format('d M Y') }}
                        ({{ \Carbon\Carbon::parse($child->date_of_birth)->age }} {{ $locale === 'ar' ? 'سنة' : 'yrs' }})
                    </p>
                @endif
            </div>
            <div class="p-4 pt-0">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'الجنس' : 'Gender' }}</td>
                        <td class="text-end">
                            @if($child->gender === 'male')
                                <span class="badge bg-info text-white">{{ $locale === 'ar' ? 'ذكر' : 'Male' }}</span>
                            @elseif($child->gender === 'female')
                                <span class="badge" style="background:#fce7f3; color:#db2777;">{{ $locale === 'ar' ? 'أنثى' : 'Female' }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'التشخيص' : 'Diagnosis' }}</td>
                        <td class="text-end">{{ $child->diagnosis ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'مستوى الدعم' : 'Support Level' }}</td>
                        <td class="text-end">{{ $child->support_level ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'تاريخ الإضافة' : 'Added On' }}</td>
                        <td class="text-end">{{ $child->created_at->format('d M Y') }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="admin-card h-100">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user"></i>
                    {{ $locale === 'ar' ? 'معلومات ولي الأمر' : 'Parent Information' }}
                </h3>
            </div>
            @if($child->user)
            <div class="p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div style="width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#0ea5e9,#0284c7); color:#fff; font-size:1.4rem; font-weight:700; display:flex; align-items:center; justify-content:center;">
                        {{ strtoupper(substr($child->user->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:1.05rem;">{{ $child->user->name }}</div>
                        <div class="text-muted">{{ $child->user->email }}</div>
                    </div>
                </div>
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'الهاتف' : 'Phone' }}</td>
                        <td>{{ $child->user->phone ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</td>
                        <td>
                            @if($child->user->is_active)
                                <span class="status-badge active">
                                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                    {{ $locale === 'ar' ? 'نشط' : 'Active' }}
                                </span>
                            @else
                                <span class="status-badge inactive">
                                    <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                    {{ $locale === 'ar' ? 'غير نشط' : 'Inactive' }}
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted fw-500">{{ $locale === 'ar' ? 'تاريخ التسجيل' : 'Joined' }}</td>
                        <td>{{ $child->user->created_at->format('d M Y') }}</td>
                    </tr>
                </table>
            </div>
            @else
            <div class="p-4">
                <p class="text-muted">{{ $locale === 'ar' ? 'لا يوجد ولي أمر مرتبط' : 'No parent linked.' }}</p>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Documents Table --}}
<div class="admin-card mb-4">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-file-alt"></i>
            {{ $locale === 'ar' ? 'الوثائق' : 'Documents' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $child->documents->count() }}
            </span>
        </h3>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'الاسم' : 'Name' }}</th>
                    <th>{{ $locale === 'ar' ? 'النوع' : 'Type' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحجم' : 'Size' }}</th>
                    <th>{{ $locale === 'ar' ? 'تاريخ الرفع' : 'Uploaded' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($child->documents as $doc)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $doc->id }}</td>
                    <td>{{ $doc->original_name ?? $doc->file_path }}</td>
                    <td>{{ $doc->mime_type ?? '—' }}</td>
                    <td>
                        @if($doc->file_size)
                            {{ number_format($doc->file_size / 1024, 1) }} KB
                        @else
                            —
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ $doc->created_at->format('d M Y') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-file-alt"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد وثائق' : 'No documents found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Memories Table --}}
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-star"></i>
            {{ $locale === 'ar' ? 'الذكريات' : 'Memories' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $child->memories->count() }}
            </span>
        </h3>
    </div>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'العنوان' : 'Title' }}</th>
                    <th>{{ $locale === 'ar' ? 'النوع' : 'Type' }}</th>
                    <th>{{ $locale === 'ar' ? 'التاريخ' : 'Date' }}</th>
                    <th>{{ $locale === 'ar' ? 'الملاحظات' : 'Notes' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($child->memories as $memory)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $memory->id }}</td>
                    <td>{{ $memory->title ?? '—' }}</td>
                    <td>{{ $memory->type ?? '—' }}</td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ isset($memory->date) ? \Carbon\Carbon::parse($memory->date)->format('d M Y') : $memory->created_at->format('d M Y') }}
                    </td>
                    <td style="max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $memory->notes ?? '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-star"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد ذكريات' : 'No memories found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
