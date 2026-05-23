@extends('layouts.admin')

@section('title', __('messages.users'))
@section('page_title', __('messages.users'))

@section('content')
@php $locale = app()->getLocale(); @endphp

{{-- Page Header --}}
<div class="page-header">
    <div class="page-header-left">
        <h1>{{ __('messages.users') }}</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}">{{ __('messages.dashboard') }}</a>
                </li>
                <li class="breadcrumb-item active">{{ __('messages.users') }}</li>
            </ol>
        </nav>
    </div>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i>
        {{ __('messages.add_user') }}
    </a>
</div>

{{-- Users Card --}}
<div class="admin-card">
    <div class="admin-card-header">
        <h3 class="admin-card-title">
            <i class="fas fa-users"></i>
            {{ __('messages.users_list') }}
            <span style="background:#ede9fe; color:#4f46e5; font-size:0.75rem; padding:2px 10px; border-radius:20px; font-weight:600;">
                {{ $users->total() }}
            </span>
        </h3>
        {{-- Search --}}
        <form method="GET" action="{{ route('admin.users.index') }}">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       name="search"
                       class="form-control"
                       placeholder="{{ __('messages.search_users') }}"
                       value="{{ request('search') }}">
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('messages.user_name') }}</th>
                    <th>{{ __('messages.email') }}</th>
                    <th>{{ __('messages.phone') }}</th>
                    <th>{{ __('messages.status') }}</th>
                    <th>{{ __('messages.created_at') }}</th>
                    <th>{{ __('messages.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td style="color:#94a3b8; font-size:0.80rem;">{{ $user->id }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar-sm">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="fw-600">{{ $user->name }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <a href="mailto:{{ $user->email }}" style="color:#4f46e5; text-decoration:none;">
                            {{ $user->email }}
                        </a>
                    </td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>
                        @if($user->is_active)
                            <span class="status-badge active">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                {{ __('messages.active') }}
                            </span>
                        @else
                            <span class="status-badge inactive">
                                <i class="fas fa-circle" style="font-size:0.5rem;"></i>
                                {{ __('messages.inactive') }}
                            </span>
                        @endif
                    </td>
                    <td style="color:#64748b; font-size:0.82rem;">
                        {{ $user->created_at->format('d M Y') }}
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <a href="{{ route('admin.users.edit', $user->id) }}"
                               class="btn-action edit"
                               title="{{ __('messages.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                            <button type="button"
                                    class="btn-action delete"
                                    title="{{ __('messages.delete') }}"
                                    onclick="confirmDelete({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>{{ __('messages.no_records') }}</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div style="padding: 16px 22px; border-top: 1px solid #f1f5f9;">
        {{ $users->links() }}
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
                    {{ __('messages.confirm_delete') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <p style="color:#475569; margin:0;">
                    {{ __('messages.delete_user_confirm') }}
                    <strong id="deleteUserName"></strong>?
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    {{ __('messages.cancel') }}
                </button>
                <form id="deleteForm" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        {{ __('messages.delete') }}
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
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteForm').action = '/{{ app()->getLocale() }}/admin/users/' + id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
@endsection
