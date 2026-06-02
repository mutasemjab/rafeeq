@extends('layouts.admin')

@section('title', 'Add Specialist')
@section('page_title', 'Add Specialist')

@section('content')
@php $locale = app()->getLocale(); @endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'إضافة متخصص' : 'Add Specialist' }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.specialists.index') }}">{{ $locale === 'ar' ? 'المتخصصون' : 'Specialists' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'إضافة' : 'Add' }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.specialists.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ $locale === 'ar' ? 'رجوع' : 'Back' }}
    </a>
</div>

<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-user-plus"></i>
            {{ $locale === 'ar' ? 'بيانات المتخصص' : 'Specialist Details' }}
        </h3>
    </div>
    <div class="p-4">
        <div class="alert alert-info d-flex align-items-start gap-2">
            <i class="fas fa-circle-info mt-1"></i>
            <div>
                {{ $locale === 'ar'
                    ? 'بعد حفظ المتخصص يمكنك إضافة مواعيد التوفر من صفحة التعديل.'
                    : 'After saving the specialist, you can add availability slots from the Edit page.' }}
            </div>
        </div>

        <form action="{{ route('admin.specialists.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-4">
                {{-- Name --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'الاسم الكامل' : 'Full Name' }} <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name') }}"
                           required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Title --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'اللقب / التخصص' : 'Title / Specialization' }}
                    </label>
                    <input type="text"
                           name="title"
                           class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title') }}"
                           placeholder="{{ $locale === 'ar' ? 'مثال: معالج نطق' : 'e.g. Speech Therapist' }}">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Bio --}}
                <div class="col-12">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'نبذة شخصية' : 'Bio' }}
                    </label>
                    <textarea name="bio"
                              class="form-control @error('bio') is-invalid @enderror"
                              rows="4"
                              placeholder="{{ $locale === 'ar' ? 'اكتب نبذة عن المتخصص...' : 'Write a brief bio...' }}">{{ old('bio') }}</textarea>
                    @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Session Fee --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'رسوم الجلسة' : 'Session Fee' }}
                    </label>
                    <input type="number"
                           name="session_fee"
                           class="form-control @error('session_fee') is-invalid @enderror"
                           value="{{ old('session_fee') }}"
                           min="0"
                           step="0.01">
                    @error('session_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Currency --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'العملة' : 'Currency' }}
                    </label>
                    <select name="currency" class="form-select @error('currency') is-invalid @enderror">
                        <option value="USD" {{ old('currency') === 'USD' ? 'selected' : '' }}>USD</option>
                        <option value="JOD" {{ old('currency') === 'JOD' ? 'selected' : '' }}>JOD</option>
                        <option value="SAR" {{ old('currency') === 'SAR' ? 'selected' : '' }}>SAR</option>
                        <option value="AED" {{ old('currency') === 'AED' ? 'selected' : '' }}>AED</option>
                        <option value="EUR" {{ old('currency') === 'EUR' ? 'selected' : '' }}>EUR</option>
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Avatar --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'الصورة الشخصية' : 'Profile Photo' }}
                    </label>
                    <input type="file"
                           name="avatar"
                           class="form-control @error('avatar') is-invalid @enderror"
                           accept="image/*">
                    @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Specializations --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'التخصصات (مفصولة بفاصلة)' : 'Specializations (comma-separated)' }}
                    </label>
                    <input type="text"
                           name="specializations"
                           class="form-control @error('specializations') is-invalid @enderror"
                           value="{{ old('specializations') }}"
                           placeholder="{{ $locale === 'ar' ? 'مثال: التوحد, اضطرابات النطق' : 'e.g. Autism, Speech Disorders' }}">
                    @error('specializations')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Languages --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'اللغات (مفصولة بفاصلة)' : 'Languages (comma-separated)' }}
                    </label>
                    <input type="text"
                           name="languages"
                           class="form-control @error('languages') is-invalid @enderror"
                           value="{{ old('languages') }}"
                           placeholder="{{ $locale === 'ar' ? 'مثال: العربية, الإنجليزية' : 'e.g. Arabic, English' }}">
                    @error('languages')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Checkboxes --}}
                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_active"
                                       id="is_active"
                                       value="1"
                                       {{ old('is_active', 1) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="is_active">
                                    {{ $locale === 'ar' ? 'نشط' : 'Active' }}
                                </label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_available"
                                       id="is_available"
                                       value="1"
                                       {{ old('is_available', 1) ? 'checked' : '' }}>
                                <label class="form-check-label fw-500" for="is_available">
                                    {{ $locale === 'ar' ? 'متاح للحجز' : 'Available for Booking' }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="col-12 d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        {{ $locale === 'ar' ? 'حفظ المتخصص' : 'Save Specialist' }}
                    </button>
                    <a href="{{ route('admin.specialists.index') }}" class="btn btn-secondary">
                        {{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
