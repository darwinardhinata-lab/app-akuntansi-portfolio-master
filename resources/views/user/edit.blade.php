@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_settings') => '#', __('erp.bc_user_management') => route('users.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto px-0">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-edit me-2 text-primary"></i> {{ __('erp.edit_user') }}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('users.update', $user->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('erp.full_name') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('erp.email_label') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('erp.password_leave_blank_hint') }}</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">{{ __('erp.role_access') }}</label>
                    <select name="role" class="form-select">
                        <option value="STAFF" {{ $user->role == 'STAFF' ? 'selected' : '' }}>{{ __('erp.role_staff') }}</option>
                        <option value="FINANCE" {{ $user->role == 'FINANCE' ? 'selected' : '' }}>{{ __('erp.role_finance') }}</option>
                        <option value="ADMIN" {{ $user->role == 'ADMIN' ? 'selected' : '' }}>{{ __('erp.role_admin') }}</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning fw-bold">{{ __('erp.update_btn') }}</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary fw-bold">{{ __('erp.cancel') }}</a>
            </form>
        </div>
    </div>
</div>
@endsection
