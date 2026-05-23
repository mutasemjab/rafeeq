@extends('layouts.admin')

@section('title', 'Batch Upload Knowledge')
@section('page_title', 'Batch Upload Knowledge')

@section('css')
<style>
.knowledge-upload-shell {
    display: grid;
    gap: 24px;
}

.upload-hero {
    position: relative;
    overflow: hidden;
    border-radius: 24px;
    padding: 28px;
    background:
        radial-gradient(circle at top right, rgba(124, 58, 237, 0.20), transparent 35%),
        linear-gradient(135deg, #0f172a 0%, #1e1b4b 45%, #312e81 100%);
    color: #fff;
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.18);
}

.upload-hero::after {
    content: '';
    position: absolute;
    inset-inline-end: -60px;
    bottom: -90px;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
}

.upload-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    padding: 8px 14px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    font-size: 0.78rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.upload-hero h2 {
    margin: 0 0 10px;
    font-size: 2rem;
    font-weight: 700;
}

.upload-hero p {
    max-width: 760px;
    margin: 0;
    color: rgba(255, 255, 255, 0.82);
    line-height: 1.8;
}

.upload-hero-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 22px;
}

.hero-stat {
    padding: 16px 18px;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.10);
}

.hero-stat .label {
    display: block;
    margin-bottom: 6px;
    color: rgba(255, 255, 255, 0.68);
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
}

.hero-stat .value {
    font-size: 1.25rem;
    font-weight: 700;
}

.batch-panel {
    border-radius: 22px;
}

.dropzone {
    position: relative;
    padding: 28px;
    border: 2px dashed #c4b5fd;
    border-radius: 20px;
    background:
        linear-gradient(180deg, rgba(245, 243, 255, 0.95) 0%, rgba(238, 242, 255, 0.95) 100%);
    text-align: center;
    cursor: pointer;
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.dropzone:hover,
.dropzone.is-dragover {
    transform: translateY(-2px);
    border-color: #8b5cf6;
    box-shadow: 0 18px 36px rgba(124, 58, 237, 0.14);
}

.dropzone-icon {
    width: 68px;
    height: 68px;
    margin: 0 auto 14px;
    border-radius: 20px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, #7c3aed 0%, #4338ca 100%);
    color: #fff;
    font-size: 1.55rem;
}

.dropzone h4 {
    margin-bottom: 6px;
    font-size: 1.1rem;
    color: #312e81;
}

.dropzone p {
    margin: 0;
    color: #6366f1;
    font-size: 0.92rem;
}

.batch-support {
    margin-top: 18px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.support-chip {
    padding: 7px 12px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.8rem;
    font-weight: 600;
}

.batch-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
}

.summary-card {
    border-radius: 18px;
    padding: 16px;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 16px 28px rgba(15, 23, 42, 0.05);
}

.summary-card .summary-label {
    color: #64748b;
    font-size: 0.82rem;
}

.summary-card .summary-value {
    margin-top: 8px;
    font-size: 1.6rem;
    font-weight: 700;
    color: #0f172a;
}

.summary-card.processed .summary-value { color: #047857; }
.summary-card.failed .summary-value { color: #b91c1c; }
.summary-card.queued .summary-value { color: #4338ca; }

.batch-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
}

.queue-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.queue-meta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 14px;
    border-radius: 999px;
    background: #eef2ff;
    color: #4338ca;
    font-size: 0.86rem;
    font-weight: 600;
}

.queue-list {
    display: grid;
    gap: 14px;
}

.queue-empty {
    padding: 44px 24px;
    border: 1px dashed #dbe4ff;
    border-radius: 20px;
    background: linear-gradient(180deg, #f8fbff 0%, #f8fafc 100%);
    text-align: center;
    color: #64748b;
}

.queue-empty i {
    font-size: 2rem;
    color: #8b5cf6;
    margin-bottom: 12px;
}

.queue-item {
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 16px 18px;
    background: #fff;
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.05);
}

.queue-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
}

.queue-file {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}

.queue-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 16px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 1.15rem;
}

.queue-icon.pdf { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
.queue-icon.doc { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
.queue-icon.pptx { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
.queue-icon.txt { background: linear-gradient(135deg, #64748b 0%, #334155 100%); }

.queue-file h5 {
    margin: 0 0 4px;
    font-size: 1rem;
    color: #0f172a;
    word-break: break-word;
}

.queue-file p {
    margin: 0;
    color: #64748b;
    font-size: 0.84rem;
    word-break: break-word;
}

.queue-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.queue-tag {
    padding: 6px 10px;
    border-radius: 999px;
    background: #f8fafc;
    color: #475569;
    font-size: 0.76rem;
    font-weight: 600;
}

.queue-status {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: capitalize;
    white-space: nowrap;
}

.queue-status.ready { background: #eef2ff; color: #4338ca; }
.queue-status.uploading { background: #fff7ed; color: #c2410c; }
.queue-status.uploaded { background: #ede9fe; color: #6d28d9; }
.queue-status.processing { background: #dbeafe; color: #1d4ed8; }
.queue-status.processed { background: #dcfce7; color: #047857; }
.queue-status.failed,
.queue-status.error { background: #fee2e2; color: #b91c1c; }

.queue-progress {
    margin-top: 14px;
}

.queue-progress-track {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: #e5e7eb;
    overflow: hidden;
}

.queue-progress-bar {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #7c3aed 0%, #2563eb 100%);
    transition: width 0.25s ease;
}

.queue-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
}

.queue-message {
    margin: 0;
    color: #475569;
    font-size: 0.84rem;
}

.queue-remove {
    border: 0;
    background: transparent;
    color: #94a3b8;
    transition: color 0.2s ease;
}

.queue-remove:hover { color: #ef4444; }

.batch-notice {
    display: none;
    margin-bottom: 14px;
    padding: 13px 16px;
    border-radius: 16px;
    font-size: 0.9rem;
}

.batch-notice.show { display: block; }
.batch-notice.info { background: #eff6ff; color: #1d4ed8; }
.batch-notice.warning { background: #fff7ed; color: #c2410c; }
.batch-notice.error { background: #fee2e2; color: #b91c1c; }
.batch-notice.success { background: #dcfce7; color: #047857; }

@media (max-width: 991px) {
    .upload-hero-grid,
    .batch-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>
@endsection

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'رفع دفعة معرفة' : 'Batch Knowledge Upload' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.knowledge.index') }}">{{ $locale === 'ar' ? 'قاعدة المعرفة' : 'Knowledge Base' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'رفع دفعة' : 'Batch Upload' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

<div class="knowledge-upload-shell">
    <section class="upload-hero">
        <span class="upload-eyebrow">
            <i class="fas fa-layer-group"></i>
            {{ $locale === 'ar' ? 'سير عمل الدفعات' : 'Batch Workflow' }}
        </span>
        <h2>{{ $locale === 'ar' ? 'ارفع حتى 50 ملفاً في كل دفعة مع متابعة مباشرة للحالة' : 'Upload up to 50 files per batch with live status tracking' }}</h2>
        <p>
            {{ $locale === 'ar'
                ? 'سيتم توليد عنوان كل ملف تلقائياً من اسم الملف نفسه، ثم رفعه وإرساله للمعالجة وعرض حالته لحظة بلحظة من الرفع وحتى اكتمال تحديث المعرفة.'
                : 'Each file title is generated automatically from the filename, then uploaded, queued, and tracked live until the knowledge update is complete.' }}
        </p>

        <div class="upload-hero-grid">
            <div class="hero-stat">
                <span class="label">{{ $locale === 'ar' ? 'الحد الأقصى' : 'Batch Limit' }}</span>
                <span class="value">50 {{ $locale === 'ar' ? 'ملفاً' : 'files' }}</span>
            </div>
            <div class="hero-stat">
                <span class="label">{{ $locale === 'ar' ? 'الأنواع المدعومة' : 'Supported Types' }}</span>
                <span class="value">PDF, DOCX, PPTX</span>
            </div>
            <div class="hero-stat">
                <span class="label">{{ $locale === 'ar' ? 'العنوان' : 'Title Source' }}</span>
                <span class="value">{{ $locale === 'ar' ? 'من اسم الملف' : 'From filename' }}</span>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="admin-card batch-panel">
                <div class="admin-card-header">
                    <h3 class="admin-card-title">
                        <i class="fas fa-folder-tree"></i>
                        {{ $locale === 'ar' ? 'إعداد الدفعة' : 'Batch Setup' }}
                    </h3>
                </div>
                <div class="p-4">
                    <form id="knowledgeBatchForm" action="{{ route('admin.knowledge.store') }}" method="POST" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label for="batchCategory" class="form-label fw-500">
                                {{ $locale === 'ar' ? 'فئة مشتركة' : 'Shared Category' }}
                            </label>
                            <input
                                type="text"
                                id="batchCategory"
                                class="form-control"
                                value="{{ old('category') }}"
                                placeholder="{{ $locale === 'ar' ? 'مثال: اللغة والنطق' : 'e.g. Language & Speech' }}"
                            >
                            <div class="form-text">
                                {{ $locale === 'ar'
                                    ? 'ستُطبّق هذه الفئة على كل الملفات في الدفعة الحالية.'
                                    : 'This category will be applied to every file in the current batch.' }}
                            </div>
                        </div>

                        <div class="dropzone" id="dropzone" role="button" tabindex="0">
                            <div class="dropzone-icon">
                                <i class="fas fa-cloud-arrow-up"></i>
                            </div>
                            <h4>{{ $locale === 'ar' ? 'اختر ملفاتك أو اسحبها هنا' : 'Pick your files or drop them here' }}</h4>
                            <p>{{ $locale === 'ar' ? 'حتى 50 ملفاً في كل مرة مع متابعة فردية لكل ملف' : 'Up to 50 files at a time with individual tracking for every file' }}</p>
                        </div>

                        <input
                            type="file"
                            id="fileInput"
                            accept=".pdf,.docx,.doc,.pptx,.txt"
                            multiple
                            hidden
                        >

                        <div class="batch-support">
                            <span class="support-chip">PDF</span>
                            <span class="support-chip">DOCX</span>
                            <span class="support-chip">DOC</span>
                            <span class="support-chip">PPTX</span>
                            <span class="support-chip">TXT</span>
                        </div>

                        <div class="batch-actions">
                            <button type="button" id="startUploadBtn" class="btn btn-primary" disabled>
                                <i class="fas fa-rocket me-1"></i>
                                {{ $locale === 'ar' ? 'ابدأ رفع الدفعة' : 'Start Batch Upload' }}
                            </button>
                            <button type="button" id="clearQueueBtn" class="btn btn-secondary" disabled>
                                {{ $locale === 'ar' ? 'مسح الدفعة' : 'Clear Batch' }}
                            </button>
                        </div>

                        <noscript>
                            <div class="alert alert-warning mt-3 mb-0">
                                {{ $locale === 'ar'
                                    ? 'واجهة الرفع الدفعي تحتاج إلى JavaScript لتتبع حالة كل ملف بشكل مباشر.'
                                    : 'The batch uploader needs JavaScript to track each file live.' }}
                            </div>
                        </noscript>
                    </form>

                    <div class="batch-summary-grid">
                        <div class="summary-card">
                            <div class="summary-label">{{ $locale === 'ar' ? 'المحددة' : 'Selected' }}</div>
                            <div class="summary-value" id="selectedCount">0</div>
                        </div>
                        <div class="summary-card queued">
                            <div class="summary-label">{{ $locale === 'ar' ? 'في المسار' : 'Queued / Live' }}</div>
                            <div class="summary-value" id="liveCount">0</div>
                        </div>
                        <div class="summary-card processed">
                            <div class="summary-label">{{ $locale === 'ar' ? 'المكتملة' : 'Processed' }}</div>
                            <div class="summary-value" id="processedCount">0</div>
                        </div>
                        <div class="summary-card failed">
                            <div class="summary-label">{{ $locale === 'ar' ? 'الفاشلة' : 'Failed' }}</div>
                            <div class="summary-value" id="failedCount">0</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="admin-card batch-panel">
                <div class="admin-card-header queue-card-header">
                    <h3 class="admin-card-title">
                        <i class="fas fa-list-check"></i>
                        {{ $locale === 'ar' ? 'قائمة ملفات الدفعة' : 'Batch Queue' }}
                    </h3>
                    <span class="queue-meta">
                        <i class="fas fa-wave-square"></i>
                        <span id="queueMetaText">{{ $locale === 'ar' ? '0 من 50 ملفات جاهزة' : '0 of 50 files ready' }}</span>
                    </span>
                </div>

                <div class="p-4">
                    <div id="batchNotice" class="batch-notice"></div>

                    <div id="queueEmpty" class="queue-empty">
                        <i class="fas fa-file-circle-plus"></i>
                        <p class="mb-1 fw-600">{{ $locale === 'ar' ? 'ابدأ بإضافة ملفات الدفعة' : 'Start by adding your batch files' }}</p>
                        <small>{{ $locale === 'ar' ? 'سيتم توليد العنوان تلقائياً من اسم كل ملف.' : 'Each file title will be generated automatically from its filename.' }}</small>
                    </div>

                    <div id="queueList" class="queue-list"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
(() => {
    const batchLimit = 50;
    const concurrency = 3;
    const locale = @json($locale);
    const copy = {
        onlyFirst50: locale === 'ar'
            ? 'تمت إضافة أول 50 ملفاً فقط. ارفع البقية في الدفعة التالية.'
            : 'Only the first 50 files were added. Upload the rest in the next batch.',
        duplicateSkipped: locale === 'ar'
            ? 'تم تجاهل الملفات المكررة داخل نفس الدفعة.'
            : 'Duplicate files in the same batch were skipped.',
        invalidType: locale === 'ar'
            ? 'تم تجاهل ملف غير مدعوم. الأنواع المتاحة: PDF, DOCX, DOC, PPTX, TXT.'
            : 'An unsupported file was skipped. Allowed types: PDF, DOCX, DOC, PPTX, TXT.',
        queueCleared: locale === 'ar'
            ? 'تم مسح الدفعة الحالية.'
            : 'The current batch was cleared.',
        readyState: locale === 'ar'
            ? 'جاهز للرفع.'
            : 'Ready to upload.',
        uploadingState: locale === 'ar'
            ? 'جارٍ رفع الملف...'
            : 'Uploading file...',
        uploadedState: locale === 'ar'
            ? 'تم الرفع. بانتظار المعالجة.'
            : 'Uploaded successfully. Waiting for processing.',
        processingState: locale === 'ar'
            ? 'جارٍ تحديث المعرفة من هذا الملف...'
            : 'Updating the knowledge base from this file...',
        failedState: locale === 'ar'
            ? 'فشلت المعالجة. يمكنك إعادة المحاولة من قائمة الوثائق.'
            : 'Processing failed. You can reprocess it from the document list.',
        uploadStarted: locale === 'ar'
            ? 'بدأت الدفعة الحالية. ستتحدث الحالة تلقائياً لكل ملف.'
            : 'The current batch has started. Each file status will update automatically.',
        batchCompleted: locale === 'ar'
            ? 'اكتمل رفع الدفعة الحالية. تابع المعالجة المباشرة حتى تنتهي كل الملفات.'
            : 'The upload phase is complete. Live processing updates will continue until every file is done.',
        networkError: locale === 'ar'
            ? 'حدثت مشكلة في الشبكة أثناء رفع الملف.'
            : 'A network problem interrupted this upload.',
        noFiles: locale === 'ar'
            ? 'اختر ملفاً واحداً على الأقل قبل البدء.'
            : 'Choose at least one file before starting.',
        queueText: (count) => locale === 'ar'
            ? `${count} من 50 ملفات في الدفعة`
            : `${count} of 50 files in this batch`,
        processedChunks: (chunks) => locale === 'ar'
            ? `تم إنشاء ${chunks} مقاطع معرفة.`
            : `${chunks} knowledge chunks were created.`,
    };

    const supportedExtensions = new Set(['pdf', 'doc', 'docx', 'pptx', 'txt']);
    const inProgressStatuses = new Set(['uploaded', 'processing']);

    const form = document.getElementById('knowledgeBatchForm');
    const fileInput = document.getElementById('fileInput');
    const dropzone = document.getElementById('dropzone');
    const startUploadBtn = document.getElementById('startUploadBtn');
    const clearQueueBtn = document.getElementById('clearQueueBtn');
    const queueList = document.getElementById('queueList');
    const queueEmpty = document.getElementById('queueEmpty');
    const batchNotice = document.getElementById('batchNotice');
    const queueMetaText = document.getElementById('queueMetaText');
    const categoryInput = document.getElementById('batchCategory');

    const selectedCount = document.getElementById('selectedCount');
    const liveCount = document.getElementById('liveCount');
    const processedCount = document.getElementById('processedCount');
    const failedCount = document.getElementById('failedCount');

    let itemId = 0;
    let activeUploads = 0;
    let pollTimer = null;
    let uploadsStarted = false;
    const items = [];

    function escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function formatBytes(bytes) {
        if (!bytes) {
            return '0 KB';
        }

        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        }

        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    function deriveTitle(filename) {
        return filename
            .replace(/\.[^.]+$/, '')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getExtension(filename) {
        const parts = filename.split('.');
        return parts.length > 1 ? parts.pop().toLowerCase() : '';
    }

    function getIconClass(extension) {
        if (extension === 'pdf') return 'pdf';
        if (extension === 'pptx') return 'pptx';
        if (extension === 'doc' || extension === 'docx') return 'doc';
        return 'txt';
    }

    function canRemove(item) {
        return !uploadsStarted || ['ready', 'failed', 'error'].includes(item.status);
    }

    function setNotice(type, message) {
        batchNotice.className = `batch-notice show ${type}`;
        batchNotice.textContent = message;
    }

    function clearNotice() {
        batchNotice.className = 'batch-notice';
        batchNotice.textContent = '';
    }

    function updateSummary() {
        const selected = items.length;
        const live = items.filter((item) => inProgressStatuses.has(item.status) || item.status === 'uploading').length;
        const processed = items.filter((item) => item.status === 'processed').length;
        const failed = items.filter((item) => ['failed', 'error'].includes(item.status)).length;

        selectedCount.textContent = selected;
        liveCount.textContent = live;
        processedCount.textContent = processed;
        failedCount.textContent = failed;
        queueMetaText.textContent = copy.queueText(selected);

        const readyCount = items.filter((item) => item.status === 'ready').length;
        startUploadBtn.disabled = readyCount === 0 || activeUploads > 0;
        clearQueueBtn.disabled = selected === 0 || activeUploads > 0;
    }

    function renderQueue() {
        if (items.length === 0) {
            queueList.innerHTML = '';
            queueEmpty.style.display = 'block';
            updateSummary();
            return;
        }

        queueEmpty.style.display = 'none';
        queueList.innerHTML = items.map((item) => {
            const extension = getExtension(item.file.name);
            const iconClass = getIconClass(extension);
            const progressValue = Math.max(0, Math.min(100, item.progress));
            const safeTitle = escapeHtml(item.title || deriveTitle(item.file.name));
            const safeOriginal = escapeHtml(item.file.name);
            const safeMessage = escapeHtml(item.message);
            const removeButton = canRemove(item)
                ? `<button type="button" class="queue-remove" data-remove-id="${item.id}" aria-label="Remove"><i class="fas fa-xmark"></i></button>`
                : '';

            return `
                <article class="queue-item" data-item-id="${item.id}">
                    <div class="queue-item-top">
                        <div class="queue-file">
                            <div class="queue-icon ${iconClass}">
                                <i class="fas ${iconClass === 'pdf' ? 'fa-file-pdf' : iconClass === 'pptx' ? 'fa-file-powerpoint' : iconClass === 'doc' ? 'fa-file-word' : 'fa-file-lines'}"></i>
                            </div>
                            <div class="min-w-0">
                                <h5>${safeTitle}</h5>
                                <p>${safeOriginal}</p>
                                <div class="queue-tags">
                                    <span class="queue-tag">${extension.toUpperCase() || 'FILE'}</span>
                                    <span class="queue-tag">${formatBytes(item.file.size)}</span>
                                    ${categoryInput.value.trim() !== '' ? `<span class="queue-tag">${escapeHtml(categoryInput.value.trim())}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="queue-status ${item.status}">
                                <i class="fas fa-circle" style="font-size:0.44rem;"></i>
                                ${escapeHtml(item.status)}
                            </span>
                            ${removeButton}
                        </div>
                    </div>
                    <div class="queue-progress">
                        <div class="queue-progress-track">
                            <div class="queue-progress-bar" style="width:${progressValue}%"></div>
                        </div>
                    </div>
                    <div class="queue-footer">
                        <p class="queue-message">${safeMessage}</p>
                        <small class="text-muted">${progressValue}%</small>
                    </div>
                </article>
            `;
        }).join('');

        queueList.querySelectorAll('[data-remove-id]').forEach((button) => {
            button.addEventListener('click', () => removeItem(Number(button.dataset.removeId)));
        });

        updateSummary();
    }

    function addFiles(fileList) {
        clearNotice();

        const incomingFiles = Array.from(fileList);
        const duplicateKeys = new Set(items.map((item) => `${item.file.name}-${item.file.size}-${item.file.lastModified}`));
        let addedCount = 0;
        let skippedDuplicates = false;
        let skippedUnsupported = false;
        let capacityWarning = false;

        for (const file of incomingFiles) {
            if (items.length >= batchLimit) {
                capacityWarning = true;
                break;
            }

            const extension = getExtension(file.name);

            if (!supportedExtensions.has(extension)) {
                skippedUnsupported = true;
                continue;
            }

            const duplicateKey = `${file.name}-${file.size}-${file.lastModified}`;

            if (duplicateKeys.has(duplicateKey)) {
                skippedDuplicates = true;
                continue;
            }

            duplicateKeys.add(duplicateKey);
            items.push({
                id: ++itemId,
                file,
                title: deriveTitle(file.name),
                status: 'ready',
                progress: 0,
                message: copy.readyState,
                documentId: null,
            });
            addedCount++;
        }

        if (capacityWarning) {
            setNotice('warning', copy.onlyFirst50);
        } else if (skippedUnsupported) {
            setNotice('warning', copy.invalidType);
        } else if (skippedDuplicates) {
            setNotice('info', copy.duplicateSkipped);
        } else if (addedCount > 0) {
            clearNotice();
        }

        renderQueue();
    }

    function removeItem(id) {
        const index = items.findIndex((item) => item.id === id);

        if (index === -1 || !canRemove(items[index])) {
            return;
        }

        items.splice(index, 1);
        renderQueue();
    }

    function clearQueue() {
        if (activeUploads > 0) {
            return;
        }

        items.splice(0, items.length);
        uploadsStarted = false;
        fileInput.value = '';
        stopPolling();
        setNotice('info', copy.queueCleared);
        renderQueue();
    }

    function parseErrorMessage(xhr) {
        try {
            const payload = JSON.parse(xhr.responseText);

            if (payload.errors) {
                const firstErrorGroup = Object.values(payload.errors)[0];
                if (Array.isArray(firstErrorGroup) && firstErrorGroup.length > 0) {
                    return firstErrorGroup[0];
                }
            }

            if (payload.message) {
                return payload.message;
            }
        } catch (error) {
        }

        return xhr.statusText || copy.networkError;
    }

    function updateItemStatus(item, status, message, progress = item.progress) {
        item.status = status;
        item.message = message;
        item.progress = progress;
        renderQueue();
    }

    function startUpload() {
        const readyItems = items.filter((item) => item.status === 'ready');

        if (readyItems.length === 0) {
            setNotice('warning', copy.noFiles);
            return;
        }

        uploadsStarted = true;
        setNotice('info', copy.uploadStarted);
        pumpQueue();
    }

    function pumpQueue() {
        while (activeUploads < concurrency) {
            const nextItem = items.find((item) => item.status === 'ready');

            if (!nextItem) {
                break;
            }

            uploadItem(nextItem);
        }

        if (activeUploads === 0 && items.every((item) => item.status !== 'ready')) {
            startPolling();
            setNotice('success', copy.batchCompleted);
        }

        updateSummary();
    }

    function uploadItem(item) {
        activeUploads++;
        updateItemStatus(item, 'uploading', copy.uploadingState, 0);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        xhr.upload.addEventListener('progress', (event) => {
            if (!event.lengthComputable) {
                return;
            }

            const percentage = Math.round((event.loaded / event.total) * 100);
            updateItemStatus(item, 'uploading', copy.uploadingState, percentage);
        });

        xhr.addEventListener('load', () => {
            activeUploads--;

            if (xhr.status >= 200 && xhr.status < 300) {
                const payload = JSON.parse(xhr.responseText);
                item.documentId = payload.id;
                updateItemStatus(item, payload.status || 'uploaded', copy.uploadedState, 100);
                startPolling();
            } else {
                updateItemStatus(item, 'error', parseErrorMessage(xhr), 0);
            }

            pumpQueue();
        });

        xhr.addEventListener('error', () => {
            activeUploads--;
            updateItemStatus(item, 'error', copy.networkError, 0);
            pumpQueue();
        });

        const data = new FormData();
        data.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        data.append('file', item.file);
        data.append('title', item.title);

        if (categoryInput.value.trim() !== '') {
            data.append('category', categoryInput.value.trim());
        }

        xhr.send(data);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    async function pollStatuses() {
        const trackedIds = items
            .filter((item) => item.documentId && inProgressStatuses.has(item.status))
            .map((item) => item.documentId);

        if (trackedIds.length === 0) {
            stopPolling();
            return;
        }

        try {
            const response = await fetch(`${@json(route('admin.knowledge.statuses'))}?ids=${trackedIds.join(',')}`, {
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

            documents.forEach((document) => {
                const item = items.find((entry) => entry.documentId === document.id);

                if (!item) {
                    return;
                }

                if (document.status === 'processing') {
                    updateItemStatus(item, 'processing', copy.processingState, 100);
                    return;
                }

                if (document.status === 'processed') {
                    const chunkCount = Number(document.chunk_count || 0);
                    updateItemStatus(item, 'processed', copy.processedChunks(chunkCount), 100);
                    return;
                }

                if (document.status === 'failed') {
                    updateItemStatus(item, 'failed', document.processing_error || copy.failedState, 100);
                    return;
                }

                if (document.status === 'uploaded') {
                    updateItemStatus(item, 'uploaded', copy.uploadedState, 100);
                }
            });
        } catch (error) {
        }
    }

    function startPolling() {
        if (pollTimer) {
            return;
        }

        pollTimer = setInterval(pollStatuses, 5000);
        pollStatuses();
    }

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        addFiles(event.dataTransfer.files);
    });

    fileInput.addEventListener('change', (event) => {
        addFiles(event.target.files);
        event.target.value = '';
    });

    startUploadBtn.addEventListener('click', startUpload);
    clearQueueBtn.addEventListener('click', clearQueue);
    categoryInput.addEventListener('input', renderQueue);

    renderQueue();
})();
</script>
@endsection
