@extends('layouts.admin')

@section('title', __('messages.edit_user'))
@section('page_title', __('messages.edit_user'))

@section('content')

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ __('messages.edit_user') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.users.index') }}">{{ __('messages.users') }}</a>
                </li>
                <li class="breadcrumb-item active">{{ __('messages.edit_user') }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i>
        {{ __('messages.back') }}
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-xl-8">
        <div class="admin-card">
            <div class="admin-card-header">
                <h3 class="admin-card-title">
                    <i class="fas fa-user-pen"></i>
                    {{ __('messages.user_information') }}
                </h3>
                <div class="user-avatar-sm" style="width:40px; height:40px; font-size:0.95rem;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
            </div>
            <div class="admin-card-body">
                <form action="{{ route('admin.users.update', $user->id) }}" method="POST" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-4">

                        {{-- Name --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="name">
                                {{ __('messages.user_name') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   id="name"
                                   name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $user->name) }}"
                                   placeholder="{{ __('messages.enter_name') }}">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Email --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="email">
                                {{ __('messages.email') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   class="form-control @error('email') is-invalid @enderror"
                                   value="{{ old('email', $user->email) }}"
                                   placeholder="{{ __('messages.enter_email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Phone --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="phone">
                                {{ __('messages.phone') }}
                            </label>
                            <input type="text"
                                   id="phone"
                                   name="phone"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   value="{{ old('phone', $user->phone) }}"
                                   placeholder="{{ __('messages.enter_phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label">{{ __('messages.status') }}</label>
                            <select name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                                <option value="1" {{ old('is_active', $user->is_active) ? 'selected' : '' }}>
                                    {{ __('messages.active') }}
                                </option>
                                <option value="0" {{ !old('is_active', $user->is_active) ? 'selected' : '' }}>
                                    {{ __('messages.inactive') }}
                                </option>
                            </select>
                            @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- New Password (optional) --}}
                        <div class="col-12">
                            <div style="padding:14px; background:#f8fafc; border-radius:10px; border:1px solid #e8ecf0;">
                                <p style="font-size:0.82rem; color:#64748b; margin:0 0 12px;">
                                    <i class="fas fa-info-circle me-1 text-info"></i>
                                    {{ __('messages.password_update_hint') }}
                                </p>
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="password">
                                            {{ __('messages.new_password') }}
                                        </label>
                                        <input type="password"
                                               id="password"
                                               name="password"
                                               class="form-control @error('password') is-invalid @enderror"
                                               placeholder="{{ __('messages.enter_password') }}">
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label" for="password_confirmation">
                                            {{ __('messages.confirm_password') }}
                                        </label>
                                        <input type="password"
                                               id="password_confirmation"
                                               name="password_confirmation"
                                               class="form-control"
                                               placeholder="{{ __('messages.confirm_password') }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-end gap-2 mt-4" style="padding-top:16px; border-top:1px solid #f1f5f9;">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                            {{ __('messages.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-floppy-disk"></i>
                            {{ __('messages.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>

        {{-- Meta info --}}
        <div class="admin-card mt-3">
            <div class="admin-card-body" style="padding:16px 22px;">
                <div class="d-flex gap-4 flex-wrap" style="font-size:0.82rem; color:#64748b;">
                    <span>
                        <i class="fas fa-calendar-plus me-1"></i>
                        {{ __('messages.created_at') }}: <strong>{{ $user->created_at->format('d M Y, H:i') }}</strong>
                    </span>
                    <span>
                        <i class="fas fa-calendar-check me-1"></i>
                        {{ __('messages.updated_at') }}: <strong>{{ $user->updated_at->format('d M Y, H:i') }}</strong>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
