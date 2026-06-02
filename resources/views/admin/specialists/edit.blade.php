@extends('layouts.admin')

@section('title', 'Edit Specialist')
@section('page_title', 'Edit Specialist')

@section('content')
@php
    $locale = app()->getLocale();
    $availabilityApiUrl = url('/api/v1/specialists/' . $specialist->id . '/availabilities');
    $specializations = is_array($specialist->specializations)
        ? implode(', ', $specialist->specializations)
        : implode(', ', json_decode($specialist->specializations ?? '[]', true));
    $languages = is_array($specialist->languages)
        ? implode(', ', $specialist->languages)
        : implode(', ', json_decode($specialist->languages ?? '[]', true));
@endphp

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ $locale === 'ar' ? 'تعديل المتخصص' : 'Edit Specialist' }}: {{ $specialist->name }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ $locale === 'ar' ? 'لوحة البيانات' : 'Dashboard' }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.specialists.index') }}">{{ $locale === 'ar' ? 'المتخصصون' : 'Specialists' }}</a>
                </li>
                <li class="breadcrumb-item active">{{ $locale === 'ar' ? 'تعديل' : 'Edit' }}</li>
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
            <i class="fas fa-user-pen"></i>
            {{ $locale === 'ar' ? 'تعديل بيانات المتخصص' : 'Edit Specialist Details' }}
        </h3>
        @if($specialist->avatar)
        <div class="d-flex align-items-center gap-2">
            <img src="{{ asset('storage/' . $specialist->avatar) }}"
                 alt="{{ $specialist->name }}"
                 style="width:48px; height:48px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;">
            <span class="fw-600">{{ $specialist->name }}</span>
        </div>
        @endif
    </div>
    <div class="p-4">
        <form action="{{ route('admin.specialists.update', $specialist->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row g-4">
                {{-- Name --}}
                <div class="col-md-6">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'الاسم الكامل' : 'Full Name' }} <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $specialist->name) }}"
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
                           value="{{ old('title', $specialist->title) }}">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Bio --}}
                <div class="col-12">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'نبذة شخصية' : 'Bio' }}
                    </label>
                    <textarea name="bio"
                              class="form-control @error('bio') is-invalid @enderror"
                              rows="4">{{ old('bio', $specialist->bio) }}</textarea>
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
                           value="{{ old('session_fee', $specialist->session_fee) }}"
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
                        @foreach(['USD','JOD','SAR','AED','EUR'] as $cur)
                        <option value="{{ $cur }}" {{ old('currency', $specialist->currency) === $cur ? 'selected' : '' }}>{{ $cur }}</option>
                        @endforeach
                    </select>
                    @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                {{-- Avatar --}}
                <div class="col-md-4">
                    <label class="form-label fw-500">
                        {{ $locale === 'ar' ? 'تغيير الصورة' : 'Change Photo' }}
                    </label>
                    <input type="file"
                           name="avatar"
                           class="form-control @error('avatar') is-invalid @enderror"
                           accept="image/*">
                    @if($specialist->avatar)
                        <small class="text-muted mt-1 d-block">
                            <i class="fas fa-image me-1"></i>
                            {{ $locale === 'ar' ? 'يوجد صورة حالية' : 'Current photo exists' }}
                        </small>
                    @endif
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
                           value="{{ old('specializations', $specializations) }}">
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
                           value="{{ old('languages', $languages) }}">
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
                                       {{ old('is_active', $specialist->is_active) ? 'checked' : '' }}>
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
                                       {{ old('is_available', $specialist->is_available) ? 'checked' : '' }}>
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
                        {{ $locale === 'ar' ? 'حفظ التغييرات' : 'Save Changes' }}
                    </button>
                    <a href="{{ route('admin.specialists.index') }}" class="btn btn-secondary">
                        {{ $locale === 'ar' ? 'إلغاء' : 'Cancel' }}
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="admin-card mt-4">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-calendar-days"></i>
            {{ $locale === 'ar' ? 'مواعيد التوفر' : 'Availability Slots' }}
        </h3>
    </div>
    <div class="p-4">
        <div class="alert alert-info">
            <div class="fw-600 mb-1">
                {{ $locale === 'ar' ? 'تظهر هذه المواعيد في الـ API عند طلب نفس التاريخ.' : 'These slots appear in the API when the requested date matches.' }}
            </div>
            <div class="small">
                {{ $locale === 'ar' ? 'مثال:' : 'Example:' }}
                <code>{{ $availabilityApiUrl }}?date=2026-06-10</code>
            </div>
            <div class="small mt-1">
                {{ $locale === 'ar'
                    ? 'إذا لم ترسل باراميتر التاريخ فسيتم إرجاع مواعيد تاريخ اليوم فقط.'
                    : 'If you do not send the date query parameter, the endpoint only returns slots for the current day.' }}
            </div>
        </div>

        <form action="{{ route('admin.specialists.availabilities.store', $specialist->id) }}" method="POST" class="row g-3 mb-4">
            @csrf

            <div class="col-md-3">
                <label class="form-label fw-500">
                    {{ $locale === 'ar' ? 'التاريخ' : 'Date' }} <span class="text-danger">*</span>
                </label>
                <input type="date"
                       name="available_date"
                       class="form-control @error('available_date', 'availability') is-invalid @enderror"
                       value="{{ old('available_date') }}"
                       required>
                @error('available_date', 'availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label fw-500">
                    {{ $locale === 'ar' ? 'وقت البداية' : 'Start Time' }} <span class="text-danger">*</span>
                </label>
                <input type="time"
                       name="start_time"
                       class="form-control @error('start_time', 'availability') is-invalid @enderror"
                       value="{{ old('start_time') }}"
                       required>
                @error('start_time', 'availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label fw-500">
                    {{ $locale === 'ar' ? 'وقت النهاية' : 'End Time' }} <span class="text-danger">*</span>
                </label>
                <input type="time"
                       name="end_time"
                       class="form-control @error('end_time', 'availability') is-invalid @enderror"
                       value="{{ old('end_time') }}"
                       required>
                @error('end_time', 'availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label fw-500">
                    {{ $locale === 'ar' ? 'مدة الجلسة بالدقائق' : 'Slot Duration (minutes)' }} <span class="text-danger">*</span>
                </label>
                <input type="number"
                       name="slot_duration_minutes"
                       class="form-control @error('slot_duration_minutes', 'availability') is-invalid @enderror"
                       value="{{ old('slot_duration_minutes', 30) }}"
                       min="1"
                       max="1440"
                       required>
                @error('slot_duration_minutes', 'availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3">
                <label class="form-label fw-500">
                    {{ $locale === 'ar' ? 'السعة' : 'Capacity' }} <span class="text-danger">*</span>
                </label>
                <input type="number"
                       name="capacity"
                       class="form-control @error('capacity', 'availability') is-invalid @enderror"
                       value="{{ old('capacity', 1) }}"
                       min="1"
                       max="1000"
                       required>
                @error('capacity', 'availability')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input"
                           type="checkbox"
                           name="slot_is_available"
                           id="availability_is_available"
                           value="1"
                           {{ old('slot_is_available', 1) ? 'checked' : '' }}>
                    <label class="form-check-label fw-500" for="availability_is_available">
                        {{ $locale === 'ar' ? 'إظهار في الـ API' : 'Visible in API' }}
                    </label>
                </div>
            </div>

            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    {{ $locale === 'ar' ? 'إضافة موعد' : 'Add Slot' }}
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ $locale === 'ar' ? 'التاريخ' : 'Date' }}</th>
                        <th>{{ $locale === 'ar' ? 'من' : 'From' }}</th>
                        <th>{{ $locale === 'ar' ? 'إلى' : 'To' }}</th>
                        <th>{{ $locale === 'ar' ? 'المدة' : 'Duration' }}</th>
                        <th>{{ $locale === 'ar' ? 'السعة' : 'Capacity' }}</th>
                        <th>{{ $locale === 'ar' ? 'الحالة' : 'Status' }}</th>
                        <th class="text-end">{{ $locale === 'ar' ? 'إجراءات' : 'Actions' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($specialist->availabilities as $availability)
                        <tr>
                            <td>{{ $availability->available_date?->format('Y-m-d') }}</td>
                            <td>{{ \Illuminate\Support\Str::of($availability->start_time)->substr(0, 5) }}</td>
                            <td>{{ \Illuminate\Support\Str::of($availability->end_time)->substr(0, 5) }}</td>
                            <td>{{ $availability->slot_duration_minutes }} {{ $locale === 'ar' ? 'دقيقة' : 'min' }}</td>
                            <td>{{ $availability->capacity }}</td>
                            <td>
                                @if($availability->is_available)
                                    <span class="badge rounded-pill bg-success">{{ $locale === 'ar' ? 'ظاهر' : 'Visible' }}</span>
                                @else
                                    <span class="badge rounded-pill bg-secondary">{{ $locale === 'ar' ? 'مخفي' : 'Hidden' }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form action="{{ route('admin.specialists.availabilities.destroy', [$specialist->id, $availability->id]) }}" method="POST" onsubmit="return confirm('{{ $locale === 'ar' ? 'هل تريد حذف هذا الموعد؟' : 'Delete this availability slot?' }}');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                        {{ $locale === 'ar' ? 'حذف' : 'Delete' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                {{ $locale === 'ar' ? 'لا توجد مواعيد توفر حتى الآن.' : 'No availability slots added yet.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
