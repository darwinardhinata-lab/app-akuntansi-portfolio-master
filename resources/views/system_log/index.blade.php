@extends('layouts.app')

@section('header')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">{{ __('erp.system_activity_logs') }}</h1>
    </div>
</div>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="card">
        <div class="card-header">
            <h5>{{ __('erp.system_activity_history') }}</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('erp.time_label') }}</th>
                            <th>{{ __('erp.user_label') }}</th>
                            <th>{{ __('erp.action') }}</th>
                            <th>{{ __('erp.module_label') }}</th>
                            <th>{{ __('erp.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user->name ?? 'System' }}</td>
                                <td>
                                    @if($log->action === 'CREATE')
                                        <span class="badge bg-success">{{ $log->action }}</span>
                                    @elseif($log->action === 'UPDATE')
                                        <span class="badge bg-warning text-dark">{{ $log->action }}</span>
                                    @elseif($log->action === 'DELETE')
                                        <span class="badge bg-danger">{{ $log->action }}</span>
                                    @elseif($log->action === 'VOID')
                                        <span class="badge bg-secondary">{{ $log->action }}</span>
                                    @else
                                        <span class="badge bg-info">{{ $log->action }}</span>
                                    @endif
                                </td>
                                <td>{{ $log->module }}</td>
                                <td>{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center">{{ __('erp.no_log_data') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
