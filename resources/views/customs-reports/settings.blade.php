@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold mb-3">{{ __('customs_settings.title') }}</h3>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('customs-settings.update') }}" class="card shadow-sm">
        @csrf
        @method('PUT')
        <div class="card-body">
            <label for="h2h_enabled" class="form-label fw-bold">{{ __('customs_settings.mode') }}</label>
            <select id="h2h_enabled" name="h2h_enabled" class="form-select mb-3">
                <option value="0">{{ __('customs_settings.internal') }}</option>
                <option value="1" disabled>{{ __('customs_settings.h2h') }}</option>
            </select>
            <div class="alert alert-warning">{{ __('customs_settings.locked') }}</div>
            <input type="hidden" name="auto_sync_internal" value="0">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="auto_sync_internal" name="auto_sync_internal" value="1" @checked($autoSync)>
                <label class="form-check-label" for="auto_sync_internal">{{ __('customs_settings.auto_sync') }}</label>
            </div>
            <p class="text-muted">{{ __('customs_settings.scope') }}</p>
            <button class="btn btn-primary" type="submit">{{ __('customs_settings.save') }}</button>
        </div>
    </form>
</div>
@endsection