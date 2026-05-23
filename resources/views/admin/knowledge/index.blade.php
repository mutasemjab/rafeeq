@extends('layouts.admin')

@section('title', 'Knowledge Base')
@section('page_title', 'Knowledge Base')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'قاعدة المعرفة' : 'Knowledge Base' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'قاعدة المعرفة' : 'Knowledge Base' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.knowledge.create') }}" class="btn btn-primary">
        <i class="fas fa-upload"></i>
        {{ $locale === 'ar' ? 'رفع وثيقة' : 'Upload Document' }}
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-book-open"></i>
            {{ $locale === 'ar' ? 'الوثائق' : 'Documents' }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $docs->total() }}
            </span>
        </h3>
        <form method="GET" action="{{ route('admin.knowledge.index') }}">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="{{ $locale === 'ar' ? 'ابحث بالعنوان...' : 'Search by title...' }}"
                       value="{{ $search }}">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ $locale === 'ar' ? 'العنوان' : 'Title' }}</th>
                    <th>{{ $locale === 'ar' ? 'الفئة' : 'Category' }}</th>
                    <th>{{ $locale === 'ar' ? 'النوع' : 'Type' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحجم' : 'Size' }}</th>
                    <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                    <th>{{ $locale === 'ar' ? 'المقاطع' : 'Chunks' }}</th>
                    <th>{{ $locale === 'ar' ? 'تاريخ الرفع' : 'Uploaded' }}</th>
                    <th>{{ $locale === 'ar' ? 'الإجراءات' : 'Actions' }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($docs as $doc)
                @php
                    $ext = pathinfo($doc->original_name ?? $doc->file_path, PATHINFO_EXTENSION);
                    $extIcons = ['pdf' => 'fa-file-pdf text-danger', 'docx' => 'fa-file-word text-primary', 'doc' => 'fa-file-word text-primary', 'txt' => 'fa-file-lines text-secondary'];
                    $icon = $extIcons[$ext] ?? 'fa-file text-muted';
                @endphp
                <tr style="{{ $doc->trashed() ? 'opacity:0.5;' : '' }}">
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $doc->id }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas {{ $icon }} fa-lg"></i>
                            <div>
                                <div class="fw-600">{{ $doc->title }}</div>
                                <small class="text-muted">{{ $doc->original_name }}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($doc->category)
                            <span class="badge rounded-pill" style="background:#f1f5f9; color:#64748b;">
                                {{ $doc->category }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge rounded-pill bg-light text-dark text-uppercase">{{ $ext }}</span>
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        @if($doc->file_size)
                            @if($doc->file_size > 1048576)
                                {{ number_format($doc->file_size / 1048576, 1) }} MB
                            @else
                                {{ number_format($doc->file_size / 1024, 1) }} KB
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @php
                            $statusMap = [
                                'pending'    => 'status-badge pending',
                                'processing' => 'status-badge pending',
                                'processed'  => 'status-badge active',
                                'failed'     => 'status-badge inactive',
                            ];
                            $sc = $statusMap[$doc->status] ?? 'status-badge pending';
                        @endphp
                        <span class="{{ $sc }}">
                            <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                            {{ ucfirst($doc->status) }}
                        </span>
                    </td>
                    <td class="text-center">
                        {{ $doc->chunks_count ?? $doc->chunk_count ?? '—' }}
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ $doc->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            @if(!$doc->trashed())
                                <form action="{{ route('admin.knowledge.reprocess', $doc->id) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                            class="btn-action"
                                            style="background:#ede9fe; color:#4f46e5; border:1px solid #c4b5fd;"
                                            title="{{ $locale === 'ar' ? 'إعادة المعالجة' : 'Reprocess' }}">
                                        <i class="fas fa-rotate-right"></i>
                                    </button>
                                </form>
                                <button type="button"
                                        class="btn-action delete"
                                        title="{{ $locale === 'ar' ? 'حذف' : 'Delete' }}"
                                        onclick="confirmDelete({{ $doc->id }}, '{{ addslashes($doc->title) }}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            @else
                                <span class="status-badge inactive">
                                    {{ $locale === 'ar' ? 'محذوف' : 'Deleted' }}
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <p>{{ $locale === 'ar' ? 'لا توجد وثائق' : 'No documents found' }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($docs->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $docs->links() }}
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
                    {{ $locale === 'ar' ? 'هل أنت متأكد من حذف الوثيقة' : 'Are you sure you want to delete' }}
                    <strong id="deleteDocName"></strong>?
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
    document.getElementById('deleteDocName').textContent = name;
    document.getElementById('deleteForm').action = '/{{ app()->getLocale() }}/admin/knowledge/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
