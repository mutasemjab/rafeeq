@extends('layouts.admin')

@section('title', 'Children')
@section('page_title', 'Children')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'الأطفال' : 'Children' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'الأطفال' : 'Children' }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-child"></i>
            {{ $locale === 'ar' ? 'قائمة الأطفال' : 'Children List' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $children->total() }}
            </span>
        </h3>
        <form method="GET" action="{{ route('admin.children.index') }}">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="{{ $locale === 'ar' ? 'ابحث بالاسم...' : 'Search by name...' }}"
                       value="{{ $search }}">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'الاسم' : 'Name' }}</th>
                    <th>{{ $locale === 'ar' ? 'ولي الأمر' : 'Parent' }}</th>
                    <th>{{ $locale === 'ar' ? 'الجنس' : 'Gender' }}</th>
                    <th>{{ $locale === 'ar' ? 'التشخيص' : 'Diagnosis' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'تاريخ الإنشاء' : 'Created' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($children as $child)
                <tr style="{{ $child->trashed() ? 'opacity:0.5;' : '' }}">
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $child->id }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm">
                                {{ strtoupper(substr($child->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-600">{{ $child->name }}</div>
                                @if($child->date_of_birth)
                                    <small class="text-muted">
                                        {{ \Carbon\Carbon::parse($child->date_of_birth)->age }}
                                        {{ $locale === 'ar' ? 'سنة' : 'yrs' }}
                                    </small>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($child->user)
                            <div>{{ $child->user->name }}</div>
                            <small class="text-muted">{{ $child->user->email }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($child->gender === 'male')
                            <span class="badge rounded-pill bg-info text-white">
                                <i class="fas fa-mars me-1"></i>{{ $locale === 'ar' ? 'ذكر' : 'Male' }}
                            </span>
                        @elseif($child->gender === 'female')
                            <span class="badge rounded-pill" style="background:#fce7f3; color:#db2777;">
                                <i class="fas fa-venus me-1"></i>{{ $locale === 'ar' ? 'أنثى' : 'Female' }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        {{ $child->diagnosis ?? '—' }}
                    </td>
                    <td>
                        @if($child->trashed())
                            <span class="status-badge inactive">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                {{ $locale === 'ar' ? 'محذوف' : 'Deleted' }}
                            </span>
                        @else
                            <span class="status-badge active">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                {{ $locale === 'ar' ? 'نشط' : 'Active' }}
                            </span>
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ $child->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            @if(!$child->trashed())
                                <a href="{{ route('admin.children.show', $child->id) }}"
                                   class="btn-action edit"
                                   title="{{ $locale === 'ar' ? 'عرض' : 'View' }}">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button"
                                        class="btn-action delete"
                                        title="{{ $locale === 'ar' ? 'حذف' : 'Delete' }}"
                                        onclick="confirmDelete({{ $child->id }}, '{{ addslashes($child->name) }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @else
                                <form action="{{ route('admin.children.restore', $child->id) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="btn-action"
                                            style="background:#ecfdf5; color:#10b981; border:1px solid #a7f3d0;"
                                            title="{{ $locale === 'ar' ? 'استعادة' : 'Restore' }}">
                                        <i class="fas fa-rotate-left"></i>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-child"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد سجلات' : 'No records found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($children->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $children->links() }}
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
                    {{ $locale === 'ar' ? 'هل أنت متأكد من حذف' : 'Are you sure you want to delete' }}
                    <strong id="deleteChildName"></strong>?
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
    document.getElementById('deleteChildName').textContent = name;
    document.getElementById('deleteForm').action = '/{{ app()->getLocale() }}/admin/children/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
