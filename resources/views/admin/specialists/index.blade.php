@extends('layouts.admin')

@section('title', 'Specialists')
@section('page_title', 'Specialists')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'المتخصصون' : 'Specialists' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'المتخصصون' : 'Specialists' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.specialists.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        {{ $locale === 'ar' ? 'إضافة متخصص' : 'Add Specialist' }}
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-user-doctor"></i>
            {{ $locale === 'ar' ? 'قائمة المتخصصين' : 'Specialists List' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $specialists->total() }}
            </span>
        </h3>
        <form method="GET" action="{{ route('admin.specialists.index') }}">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="{{ $locale === 'ar' ? 'ابحث بالاسم أو اللقب...' : 'Search by name or title...' }}"
                       value="{{ $search }}">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'الصورة' : 'Avatar' }}</th>
                    <th>{{ $locale === 'ar' ? 'الاسم' : 'Name' }}</th>
                    <th>{{ $locale === 'ar' ? 'اللقب' : 'Title' }}</th>
                    <th>{{ $locale === 'ar' ? 'رسوم الجلسة' : 'Session Fee' }}</th>
                    <th>{{ $locale === 'ar' ? 'التقييم' : 'Rating' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($specialists as $specialist)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $specialist->id }}</td>
                    <td>
                        @if($specialist->avatar)
                            <img src="{{ asset('storage/' . $specialist->avatar) }}"
                                 alt="{{ $specialist->name }}"
                                 style="width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;">
                        @else
                            <div class="user-avatar-sm" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);">
                                {{ strtoupper(substr($specialist->name, 0, 1)) }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <div class="fw-600">{{ $specialist->name }}</div>
                    </td>
                    <td>{{ $specialist->title ?? '—' }}</td>
                    <td>
                        @if($specialist->session_fee)
                            {{ number_format($specialist->session_fee, 2) }}
                            {{ $specialist->currency ?? '' }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($specialist->rating_avg)
                            <span style="color:#f59e0b;">
                                <i class="fas fa-star"></i>
                                {{ number_format($specialist->rating_avg, 1) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($specialist->is_active)
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
                    <td>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.specialists.edit', $specialist->id) }}"
                               class="btn-action edit"
                               title="{{ $locale === 'ar' ? 'تعديل' : 'Edit' }}">
                                <i class="fas fa-pen"></i>
                            </a>
                            <button type="button"
                                    class="btn-action delete"
                                    title="{{ $locale === 'ar' ? 'حذف' : 'Delete' }}"
                                    onclick="confirmDelete({{ $specialist->id }}, '{{ addslashes($specialist->name) }}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-user-doctor"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد سجلات' : 'No specialists found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($specialists->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $specialists->links() }}
    </div>
    @endif
</div>

{{-- Delete Confirm Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-triangle-exclamation text-danger me-2"></i>
                    {{ $locale === 'ar' ? 'تأكيد الحذف' : 'Confirm Delete' }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <p style="color:#475569; margin:0;">
                    {{ $locale === 'ar' ? 'هل أنت متأكد من حذف المتخصص' : 'Are you sure you want to delete' }}
                    <strong id="deleteSpecialistName"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    {{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        {{ $locale === 'ar' ? 'حذف' : 'Delete' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
function confirmDelete(id, name) {
    document.getElementById('deleteSpecialistName').textContent = name;
    document.getElementById('deleteForm').action = '/{{ app()->getLocale() }}/admin/specialists/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
