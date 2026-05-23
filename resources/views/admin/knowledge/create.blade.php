@extends('layouts.admin')

@section('title', 'Upload Document')
@section('page_title', 'Upload Document')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'رفع وثيقة جديدة' : 'Upload New Document' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.knowledge.index') }}">{{ $locale === 'ar' ? 'قاعدة المعرفة' : 'Knowledge Base' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'رفع' : 'Upload' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.knowledge.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-file-arrow-up"></i>
                    {{ $locale === 'ar' ? 'بيانات الوثيقة' : 'Document Details' }}
                </h3>
            </div>
            <div class="p-4">
                <form action="{{ route('admin.knowledge.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-4">
                        {{-- File Upload --}}
                        <div class="col-12">
                            <label class="form-label fw-500">
                                {{ $locale === 'ar' ? 'الملف' : 'File' }} <span class="text-danger">*</span>
                            </label>
                            <div style="border:2px dashed #c4b5fd; border-radius:12px; padding:32px; text-align:center; background:#faf5ff; cursor:pointer;"
                                 onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-arrow-up fa-3x" style="color:#7c3aed; margin-bottom:12px;"></i>
                                <p class="mb-1 fw-500" style="color:#4f46e5;">
                                    {{ $locale === 'ar' ? 'انقر لاختيار الملف' : 'Click to select file' }}
                                </p>
                                <p class="text-muted mb-0" style="font-size:0.82rem;">
                                    {{ $locale === 'ar' ? 'PDF, DOCX, DOC, TXT — حتى 50 ميغابايت' : 'PDF, DOCX, DOC, TXT — max 50 MB' }}
                                </p>
                                <div id="fileNameDisplay" class="mt-2 text-muted" style="font-size:0.85rem;"></div>
                            </div>
                            <input type="file"
                                   id="fileInput"
                                   name="file"
                                   class="@error('file') is-invalid @enderror"
                                   accept=".pdf,.docx,.doc,.txt"
                                   style="display:none;"
                                   onchange="document.getElementById('fileNameDisplay').textContent = this.files[0]?.name ?? '';"
                                   required>
                            @error('file')<div class="text-danger mt-1 small">{{ $message }}</div>@enderror
                        </div>

                        {{-- Title --}}
                        <div class="col-md-8">
                            <label class="form-label fw-500">
                                {{ $locale === 'ar' ? 'عنوان الوثيقة' : 'Document Title' }}
                            </label>
                            <input type="text"
                                   name="title"
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}"
                                   placeholder="{{ $locale === 'ar' ? 'اتركه فارغاً لاستخدام اسم الملف' : 'Leave blank to use filename' }}">
                            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Category --}}
                        <div class="col-md-4">
                            <label class="form-label fw-500">
                                {{ $locale === 'ar' ? 'الفئة' : 'Category' }}
                            </label>
                            <input type="text"
                                   name="category"
                                   class="form-control @error('category') is-invalid @enderror"
                                   value="{{ old('category') }}"
                                   placeholder="{{ $locale === 'ar' ? 'مثال: التوحد' : 'e.g. Autism' }}">
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        {{-- Info Box --}}
                        <div class="col-12">
                            <div style="background:#ede9fe; border-radius:10px; padding:16px; display:flex; gap:12px; align-items:flex-start;">
                                <i class="fas fa-circle-info" style="color:#4f46e5; margin-top:2px;"></i>
                                <div>
                                    <div class="fw-600 mb-1" style="color:#4f46e5;">
                                        {{ $locale === 'ar' ? 'ملاحظة حول المعالجة' : 'Processing Note' }}
                                    </div>
                                    <p class="mb-0 text-muted" style="font-size:0.875rem;">
                                        {{ $locale === 'ar'
                                            ? 'سيتم رفع الوثيقة وإضافتها إلى قائمة الانتظار للمعالجة بواسطة محرك الذكاء الاصطناعي. ستتغير حالتها من "قيد الانتظار" إلى "تمت المعالجة" عند اكتمال العملية.'
                                            : 'The document will be uploaded and queued for AI processing. Its status will change from "pending" to "processed" when complete.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Submit --}}
                        <div class="col-12 d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-1"></i>
                                {{ $locale === 'ar' ? 'رفع ومعالجة' : 'Upload & Process' }}
                            </button>
                            <a href="{{ route('admin.knowledge.index') }}" class="btn btn-secondary">
                                {{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
