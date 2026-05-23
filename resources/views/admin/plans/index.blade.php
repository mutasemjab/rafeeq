@extends('layouts.admin')

@section('title', 'Plans')
@section('page_title', 'Plans')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'خطط الاشتراك' : 'Subscription Plans' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'الخطط' : 'Plans' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        {{ $locale === 'ar' ? 'إضافة خطة' : 'Add Plan' }}
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-tags"></i>
            {{ $locale === 'ar' ? 'قائمة الخطط' : 'Plans List' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $plans->total() }}
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
                    <th>{{ $locale === 'ar' ? 'الفترة' : 'Billing' }}</th>
                    <th>{{ $locale === 'ar' ? 'السعر' : 'Price' }}</th>
                    <th>{{ $locale === 'ar' ? 'رسائل/يوم' : 'Msgs/Day' }}</th>
                    <th>{{ $locale === 'ar' ? 'أقصى أطفال' : 'Max Children' }}</th>
                    <th>{{ $locale === 'ar' ? 'المميزات' : 'Features' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $plan->id }}</td>
                    <td>
                        <div class="fw-600">{{ $plan->name }}</div>
                        <small class="text-muted">{{ $plan->slug }}</small>
                    </td>
                    <td>
                        @if($plan->type === 'pro')
                            <span class="badge rounded-pill" style="background:#fef3c7; color:#d97706;">
                                <i class="fas fa-crown me-1"></i>Pro
                            </span>
                        @else
                            <span class="badge rounded-pill" style="background:#f1f5f9; color:#64748b;">
                                Free
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-light text-dark">
                            {{ ucfirst($plan->billing_period) }}
                        </span>
                    </td>
                    <td>
                        <span class="fw-600">
                            {{ number_format($plan->price, 2) }}
                            {{ $plan->currency }}
                        </span>
                    </td>
                    <td class="text-center">
                        {{ $plan->ai_messages_per_day ? $plan->ai_messages_per_day : '&infin;' }}
                    </td>
                    <td class="text-center">
                        {{ $plan->max_children ? $plan->max_children : '&infin;' }}
                    </td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            @if($plan->has_specialist_access)
                                <span class="badge rounded-pill bg-info text-white" title="{{ $locale === 'ar' ? 'وصول للمتخصصين' : 'Specialist Access' }}">
                                    <i class="fas fa-user-doctor"></i>
                                </span>
                            @endif
                            @if($plan->has_voice_mode)
                                <span class="badge rounded-pill" style="background:#ede9fe; color:#4f46e5;" title="{{ $locale === 'ar' ? 'الوضع الصوتي' : 'Voice Mode' }}">
                                    <i class="fas fa-microphone"></i>
                                </span>
                            @endif
                            @if($plan->has_progress_reports)
                                <span class="badge rounded-pill" style="background:#ecfdf5; color:#10b981;" title="{{ $locale === 'ar' ? 'تقارير التقدم' : 'Progress Reports' }}">
                                    <i class="fas fa-chart-line"></i>
                                </span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($plan->is_active)
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
                            <a href="{{ route('admin.plans.edit', $plan->id) }}"
                               class="btn-action edit"
                               title="{{ $locale === 'ar' ? 'تعديل' : 'Edit' }}">
                                <i class="fas fa-pen"></i>
                            </a>
                            <button type="button"
                                    class="btn-action delete"
                                    title="{{ $locale === 'ar' ? 'حذف' : 'Delete' }}"
                                    onclick="confirmDelete({{ $plan->id }}, '{{ addslashes($plan->name) }}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد خطط' : 'No plans found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($plans->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $plans->links() }}
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
                    {{ $locale === 'ar' ? 'هل أنت متأكد من حذف الخطة' : 'Are you sure you want to delete plan' }}
                    <strong id="deletePlanName"></strong>?
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
    document.getElementById('deletePlanName').textContent = name;
    document.getElementById('deleteForm').action = '/{{ app()->getLocale() }}/admin/plans/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
