@extends('layouts.admin')

@section('title', 'Knowledge Base')
@section('page_title', 'Knowledge Base')

@section('css')
<style>
.knowledge-shell {
    display: grid;
    gap: 22px;
}

.knowledge-summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 14px;
}

.knowledge-summary-card {
    border-radius: 20px;
    padding: 18px;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 16px 30px rgba(15, 23, 42, 0.05);
}

.knowledge-summary-card .label {
    display: block;
    color: #64748b;
    font-size: 0.82rem;
}

.knowledge-summary-card .value {
    display: block;
    margin-top: 8px;
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
}

.knowledge-summary-card.live .value { color: #4338ca; }
.knowledge-summary-card.success .value { color: #047857; }
.knowledge-summary-card.failed .value { color: #b91c1c; }

.knowledge-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
}

.knowledge-filter-form {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.knowledge-filter-form .search-wrapper {
    min-width: 280px;
}

.knowledge-filter-select {
    min-width: 180px;
}

.live-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.86rem;
    font-weight: 600;
}

.live-dot {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #4f46e5;
    box-shadow: 0 0 0 8px rgba(79, 70, 229, 0.16);
    animation: livePulse 1.8s infinite;
}

@keyframes livePulse {
    0%,
    100% { transform: scale(1); }
    50% { transform: scale(1.18); }
}

.knowledge-status-stack {
    display: grid;
    gap: 6px;
}

.knowledge-substatus {
    color: #64748b;
    font-size: 0.8rem;
    line-height: 1.5;
}

.knowledge-row-failed {
    margin-top: 8px;
    padding: 9px 11px;
    border-radius: 12px;
    background: #fff1f2;
    color: #b91c1c;
    font-size: 0.78rem;
}

.knowledge-title-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.knowledge-file-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 1.05rem;
    flex-shrink: 0;
}

.knowledge-file-icon.pdf { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
.knowledge-file-icon.doc { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
.knowledge-file-icon.pptx { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
.knowledge-file-icon.txt { background: linear-gradient(135deg, #64748b 0%, #334155 100%); }

.knowledge-title-meta {
    min-width: 0;
}

.knowledge-title-meta .title {
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
}

.knowledge-title-meta .original {
    color: #64748b;
    font-size: 0.82rem;
    word-break: break-word;
}

.knowledge-uploaded-at {
    display: grid;
    gap: 4px;
    color: #64748b;
    font-size: 0.82rem;
}

@media (max-width: 1199px) {
    .knowledge-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .knowledge-summary-grid {
        grid-template-columns: 1fr;
    }

    .knowledge-filter-form {
        width: 100%;
    }

    .knowledge-filter-form .search-wrapper,
    .knowledge-filter-select {
        width: 100%;
        min-width: 0;
    }
}
</style>
@endsection

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
        {{ $locale === 'ar' ? 'رفع دفعة جديدة' : 'Upload New Batch' }}
    </a>
</div>

<div class="knowledge-shell">
    <section class="knowledge-summary-grid">
        <article class="knowledge-summary-card">
            <span class="label">{{ $locale === 'ar' ? 'إجمالي الوثائق' : 'Total Documents' }}</span>
            <span class="value">{{ $stats['total'] }}</span>
        </article>
        <article class="knowledge-summary-card live">
            <span class="label">{{ $locale === 'ar' ? 'تم الرفع' : 'Uploaded' }}</span>
            <span class="value">{{ $stats['uploaded'] }}</span>
        </article>
        <article class="knowledge-summary-card live">
            <span class="label">{{ $locale === 'ar' ? 'قيد المعالجة' : 'Processing' }}</span>
            <span class="value">{{ $stats['processing'] }}</span>
        </article>
        <article class="knowledge-summary-card success">
            <span class="label">{{ $locale === 'ar' ? 'تمت المعالجة' : 'Processed' }}</span>
            <span class="value">{{ $stats['processed'] }}</span>
        </article>
        <article class="knowledge-summary-card failed">
            <span class="label">{{ $locale === 'ar' ? 'فشلت' : 'Failed' }}</span>
            <span class="value">{{ $stats['failed'] }}</span>
        </article>
    </section>

    <div class="admin-card">
        <div class="admin-card-header knowledge-filter-bar">
            <div>
                <h3 class="admin-card-title">
                    <i class="fas fa-book-open"></i>
                    {{ $locale === 'ar' ? 'مكتبة الوثائق' : 'Document Library' }}
                    <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                        {{ $docs->total() }}
                    </span>
                </h3>
            </div>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                @if($liveIds->isNotEmpty())
                    <span class="live-pill">
                        <span class="live-dot"></span>
                        {{ $locale === 'ar' ? 'تحديث مباشر لحالات الملفات الجارية' : 'Live status updates for active files' }}
                    </span>
                @endif

                <form method="GET" action="{{ route('admin.knowledge.index') }}" class="knowledge-filter-form">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input
                            type="text"
                            name="search"
                            class="form-control"
                            placeholder="{{ $locale === 'ar' ? 'ابحث بالعنوان...' : 'Search by title...' }}"
                            value="{{ $search }}"
                        >
                    </div>

                    <select name="status" class="form-select knowledge-filter-select">
                        <option value="all" {{ !$status || $status === 'all' ? 'selected' : '' }}>{{ $locale === 'ar' ? 'كل الحالات' : 'All statuses' }}</option>
                        <option value="uploaded" {{ $status === 'uploaded' ? 'selected' : '' }}>{{ $locale === 'ar' ? 'تم الرفع' : 'Uploaded' }}</option>
                        <option value="processing" {{ $status === 'processing' ? 'selected' : '' }}>{{ $locale === 'ar' ? 'قيد المعالجة' : 'Processing' }}</option>
                        <option value="processed" {{ $status === 'processed' ? 'selected' : '' }}>{{ $locale === 'ar' ? 'تمت المعالجة' : 'Processed' }}</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>{{ $locale === 'ar' ? 'فشلت' : 'Failed' }}</option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        {{ $locale === 'ar' ? 'تصفية' : 'Filter' }}
                    </button>
                </form>
            </div>
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
                            $ext = strtolower(pathinfo($doc->original_name ?? $doc->file_path, PATHINFO_EXTENSION));
                            $iconClass = match ($ext) {
                                'pdf' => 'pdf',
                                'pptx' => 'pptx',
                                'doc', 'docx' => 'doc',
                                default => 'txt',
                            };
                            $badgeClass = match ($doc->status) {
                                'processed' => 'status-badge active',
                                'failed' => 'status-badge inactive',
                                default => 'status-badge pending',
                            };
                        @endphp
                        <tr
                            data-knowledge-row
                            data-document-id="{{ $doc->id }}"
                            style="{{ $doc->trashed() ? 'opacity:0.5;' : '' }}"
                        >
                            <td style="color:#94a3b8; font-size:0.80rem;">{{ $doc->id }}</td>
                            <td>
                                <div class="knowledge-title-cell">
                                    <div class="knowledge-file-icon {{ $iconClass }}">
                                        <i class="fas {{ $iconClass === 'pdf' ? 'fa-file-pdf' : ($iconClass === 'pptx' ? 'fa-file-powerpoint' : ($iconClass === 'doc' ? 'fa-file-word' : 'fa-file-lines')) }}"></i>
                                    </div>
                                    <div class="knowledge-title-meta">
                                        <div class="title">{{ $doc->title }}</div>
                                        <div class="original">{{ $doc->original_name }}</div>
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
                                <span class="badge rounded-pill bg-light text-dark text-uppercase">{{ $ext ?: 'file' }}</span>
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
                                <div class="knowledge-status-stack">
                                    <span class="{{ $badgeClass }}" data-status-badge>
                                        <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                        <span data-status-text>{{ ucfirst($doc->status) }}</span>
                                    </span>
                                    <small class="knowledge-substatus" data-status-note>
                                        @if($doc->status === 'uploaded')
                                            {{ $locale === 'ar' ? 'بانتظار عامل المعالجة أو بدء الطابور.' : 'Waiting for the queue worker to begin processing.' }}
                                        @elseif($doc->status === 'processing')
                                            {{ $locale === 'ar' ? 'يتم استخراج المحتوى وتحديث المعرفة الآن.' : 'Extracting content and updating the knowledge base now.' }}
                                        @elseif($doc->status === 'processed')
                                            {{ $locale === 'ar' ? 'الوثيقة جاهزة للبحث والاستخدام.' : 'Ready for search and assistant responses.' }}
                                        @else
                                            {{ $locale === 'ar' ? 'تحتاج إلى مراجعة سبب الفشل أو إعادة المعالجة.' : 'Review the failure reason or reprocess the file.' }}
                                        @endif
                                    </small>
                                    <div class="knowledge-row-failed" data-status-error style="{{ $doc->status === 'failed' && $doc->processing_error ? '' : 'display:none;' }}">
                                        {{ $doc->processing_error }}
                                    </div>
                                </div>
                            </td>
                            <td class="text-center" data-chunk-count>{{ $doc->chunks_count ?? 0 }}</td>
                            <td>
                                <div class="knowledge-uploaded-at">
                                    <span>{{ $doc->created_at->format('d M Y') }}</span>
                                    <small>{{ $doc->created_at->format('h:i A') }}</small>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    @if(!$doc->trashed())
                                        <form action="{{ route('admin.knowledge.reprocess', $doc->id) }}" method="POST">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="btn-action"
                                                style="background:#ede9fe; color:#4f46e5; border:1px solid #c4b5fd;"
                                                title="{{ $locale === 'ar' ? 'إعادة المعالجة' : 'Reprocess' }}"
                                            >
                                                <i class="fas fa-rotate-right"></i>
                                            </button>
                                        </form>
                                        <button
                                            type="button"
                                            class="btn-action delete"
                                            title="{{ $locale === 'ar' ? 'حذف' : 'Delete' }}"
                                            onclick="confirmDelete({{ $doc->id }}, '{{ addslashes($doc->title) }}')"
                                        >
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
                                    <p>{{ $locale === 'ar' ? 'لا توجد وثائق مطابقة حالياً' : 'No matching documents found right now' }}</p>
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
</div>

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

(() => {
    const liveIds = @json($liveIds->values());

    if (!Array.isArray(liveIds) || liveIds.length === 0) {
        return;
    }

    const locale = @json($locale);
    const statusUrl = @json(route('admin.knowledge.statuses'));
    const activeIds = new Set(liveIds.map((id) => Number(id)));

    const badgeClassFor = (status) => {
        if (status === 'processed') return 'status-badge active';
        if (status === 'failed') return 'status-badge inactive';
        return 'status-badge pending';
    };

    const noteFor = (status, chunkCount) => {
        if (status === 'uploaded') {
            return locale === 'ar'
                ? 'بانتظار عامل المعالجة أو بدء الطابور.'
                : 'Waiting for the queue worker to begin processing.';
        }

        if (status === 'processing') {
            return locale === 'ar'
                ? 'يتم استخراج المحتوى وتحديث المعرفة الآن.'
                : 'Extracting content and updating the knowledge base now.';
        }

        if (status === 'processed') {
            return locale === 'ar'
                ? `جاهزة الآن مع ${chunkCount || 0} مقاطع معرفة.`
                : `Ready now with ${chunkCount || 0} knowledge chunks.`;
        }

        return locale === 'ar'
            ? 'تحتاج إلى مراجعة سبب الفشل أو إعادة المعالجة.'
            : 'Review the failure reason or reprocess the file.';
    };

    async function pollStatuses() {
        if (activeIds.size === 0) {
            clearInterval(timer);
            return;
        }

        const ids = Array.from(activeIds);

        try {
            const response = await fetch(`${statusUrl}?ids=${ids.join(',')}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const documents = Array.isArray(payload.data) ? payload.data : [];

            documents.forEach((docPayload) => {
                const row = window.document.querySelector(`[data-document-id="${docPayload.id}"]`);

                if (!row) {
                    activeIds.delete(Number(docPayload.id));
                    return;
                }

                const badge = row.querySelector('[data-status-badge]');
                const statusText = row.querySelector('[data-status-text]');
                const statusNote = row.querySelector('[data-status-note]');
                const statusError = row.querySelector('[data-status-error]');
                const chunkCount = row.querySelector('[data-chunk-count]');

                if (badge) {
                    badge.className = badgeClassFor(docPayload.status);
                }

                if (statusText) {
                    statusText.textContent = docPayload.status.charAt(0).toUpperCase() + docPayload.status.slice(1);
                }

                if (statusNote) {
                    statusNote.textContent = noteFor(docPayload.status, docPayload.chunk_count);
                }

                if (chunkCount) {
                    chunkCount.textContent = docPayload.chunk_count ?? 0;
                }

                if (statusError) {
                    if (docPayload.status === 'failed' && docPayload.processing_error) {
                        statusError.style.display = 'block';
                        statusError.textContent = docPayload.processing_error;
                    } else {
                        statusError.style.display = 'none';
                        statusError.textContent = '';
                    }
                }

                if (!['uploaded', 'processing'].includes(docPayload.status)) {
                    activeIds.delete(Number(docPayload.id));
                }
            });

            if (activeIds.size === 0) {
                clearInterval(timer);
            }
        } catch (error) {
        }
    }

    const timer = setInterval(pollStatuses, 5000);
    pollStatuses();
})();
</script>
@endsection
