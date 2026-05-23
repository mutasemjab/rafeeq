@extends('layouts.admin')

@section('title', __('messages.add_user'))
@section('page_title', __('messages.add_user'))

@section('content')

<div class="page-header">
    <div class="page-header-left">
        <h1>{{ __('messages.add_user') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.users.index') }}">{{ __('messages.users') }}</a>
                </li>
                <li class="breadcrumb-item active">{{ __('messages.add_user') }}</li>
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
                    <i class="fas fa-user-plus"></i>
                    {{ __('messages.user_information') }}
                </h3>
            </div>
            <div class="admin-card-body">
                <form action="{{ route('admin.users.store') }}" method="POST" novalidate>
                    @csrf

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
                                   value="{{ old('name') }}"
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
                                   value="{{ old('email') }}"
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
                                   value="{{ old('phone') }}"
                                   placeholder="{{ __('messages.enter_phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Status --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label">{{ __('messages.status') }}</label>
                            <select name="is_active" class="form-select @error('is_active') is-invalid @enderror">
                                <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>
                                    {{ __('messages.active') }}
                                </option>
                                <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>
                                    {{ __('messages.inactive') }}
                                </option>
                            </select>
                            @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password">
                                {{ __('messages.password') }}
                                <span class="text-danger">*</span>
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

                        {{-- Password Confirmation --}}
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="password_confirmation">
                                {{ __('messages.confirm_password') }}
                                <span class="text-danger">*</span>
                            </label>
                            <input type="password"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   class="form-control"
                                   placeholder="{{ __('messages.confirm_password') }}">
                        </div>

                    </div>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-end gap-2 mt-4" style="padding-top:16px; border-top:1px solid #f1f5f9;">
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                            {{ __('messages.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                            {{ __('messages.save') }}
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

@endsection
