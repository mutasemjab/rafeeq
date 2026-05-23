@extends('layouts.admin')

@section('title', 'Edit Plan')
@section('page_title', 'Edit Plan')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'تعديل الخطة' : 'Edit Plan' }}: {{ $plan->name }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.plans.index') }}">{{ $locale === 'ar' ? 'الخطط' : 'Plans' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'تعديل' : 'Edit' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.plans.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-pen-to-square"></i>
            {{ $locale === 'ar' ? 'تعديل بيانات الخطة' : 'Edit Plan Details' }}
        </h3>
    </div>
    <div class="p-4">
        <form action="{{ route('admin.plans.update', $plan->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-4">
                {{-- Name --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'اسم الخطة' : 'Plan Name' }} <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $plan->name) }}"
                           required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Type --}}
                <div class="col-md-3">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'نوع الخطة' : 'Plan Type' }} <span class="text-danger">*</span>
                    </label>
                    <select name="type" class="form-select @error('type') is-invalid @enderror" required>
                        <option value="free" {{ old('type', $plan->type) === 'free' ? 'selected' : '' }}>
                            {{ $locale === 'ar' ? 'مجانية' : 'Free' }}
                        </option>
                        <option value="pro" {{ old('type', $plan->type) === 'pro' ? 'selected' : '' }}>
                            {{ $locale === 'ar' ? 'مدفوعة' : 'Pro' }}
                        </option>
                    </select>
                    @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Billing Period --}}
                <div class="col-md-3">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'فترة الفوترة' : 'Billing Period' }} <span class="text-danger">*</span>
                    </label>
                    <select name="billing_period" class="form-select @error('billing_period') is-invalid @enderror" required>
                        @foreach(['monthly','yearly','lifetime'] as $period)
                        <option value="{{ $period }}" {{ old('billing_period', $plan->billing_period) === $period ? 'selected' : '' }}>
                            {{ ucfirst($period) }}
                        </option>
                        @endforeach
                    </select>
                    @error('billing_period')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Price --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'السعر' : 'Price' }} <span class="text-danger">*</span>
                    </label>
                    <input type="number"
                           name="price"
                           class="form-control @error('price') is-invalid @enderror"
                           value="{{ old('price', $plan->price) }}"
                           min="0"
                           step="0.01"
                           required>
                    @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Currency --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'العملة' : 'Currency' }} <span class="text-danger">*</span>
                    </label>
                    <select name="currency" class="form-select @error('currency') is-invalid @enderror" required>
                        @foreach(['USD','JOD','SAR','AED','EUR'] as $cur)
                        <option value="{{ $cur }}" {{ old('currency', $plan->currency) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <hr style="border-color:#f1f5f9;">
                    <h6 class="text-muted mb-0">
                        <i class="fas fa-sliders me-1"></i>
                        {{ $locale === 'ar' ? 'حدود الاستخدام' : 'Usage Limits' }}
                        <small class="ms-2" style="font-weight:400;">{{ $locale === 'ar' ? '(اتركها فارغة لعدم وجود حد)' : '(leave blank for unlimited)' }}</small>
                    </h6>
                </div>

                {{-- AI Messages / Day --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'رسائل الذكاء الاصطناعي / يوم' : 'AI Messages / Day' }}
                    </label>
                    <input type="number"
                           name="ai_messages_per_day"
                           class="form-control @error('ai_messages_per_day') is-invalid @enderror"
                           value="{{ old('ai_messages_per_day', $plan->ai_messages_per_day) }}"
                           min="1"
                           placeholder="{{ $locale === 'ar' ? 'غير محدود' : 'Unlimited' }}">
                    @error('ai_messages_per_day')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Max Children --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'أقصى عدد أطفال' : 'Max Children' }}
                    </label>
                    <input type="number"
                           name="max_children"
                           class="form-control @error('max_children') is-invalid @enderror"
                           value="{{ old('max_children', $plan->max_children) }}"
                           min="1"
                           placeholder="{{ $locale === 'ar' ? 'غير محدود' : 'Unlimited' }}">
                    @error('max_children')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Max Documents per Child --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'أقصى وثائق لكل طفل' : 'Max Docs / Child' }}
                    </label>
                    <input type="number"
                           name="max_documents_per_child"
                           class="form-control @error('max_documents_per_child') is-invalid @enderror"
                           value="{{ old('max_documents_per_child', $plan->max_documents_per_child) }}"
                           min="1"
                           placeholder="{{ $locale === 'ar' ? 'غير محدود' : 'Unlimited' }}">
                    @error('max_documents_per_child')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Feature Checkboxes --}}
                <div class="col-12">
                    <hr style="border-color:#f1f5f9;">
                    <h6 class="text-muted mb-3">
                        <i class="fas fa-star me-1"></i>
                        {{ $locale === 'ar' ? 'الميزات المتاحة' : 'Available Features' }}
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_specialist_access"
                                       id="has_specialist_access" value="1"
                                       {{ old('has_specialist_access', $plan->has_specialist_access) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="has_specialist_access">
                                    <i class="fas fa-user-doctor text-info me-1"></i>
                                    {{ $locale === 'ar' ? 'وصول للمتخصصين' : 'Specialist Access' }}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_voice_mode"
                                       id="has_voice_mode" value="1"
                                       {{ old('has_voice_mode', $plan->has_voice_mode) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="has_voice_mode">
                                    <i class="fas fa-microphone" style="color:#4f46e5;"></i>
                                    {{ $locale === 'ar' ? 'الوضع الصوتي' : 'Voice Mode' }}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_progress_reports"
                                       id="has_progress_reports" value="1"
                                       {{ old('has_progress_reports', $plan->has_progress_reports) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="has_progress_reports">
                                    <i class="fas fa-chart-line text-success me-1"></i>
                                    {{ $locale === 'ar' ? 'تقارير التقدم' : 'Progress Reports' }}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active"
                                       id="is_active" value="1"
                                       {{ old('is_active', $plan->is_active) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="is_active">
                                    <i class="fas fa-circle-check text-success me-1"></i>
                                    {{ $locale === 'ar' ? 'نشط' : 'Active' }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="col-12 d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        {{ $locale === 'ar' ? 'حفظ التغييرات' : 'Save Changes' }}
                    </button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-secondary">
                        {{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
