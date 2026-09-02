@extends('layouts.app')

@section('header')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3">System Activity Logs</h1>
    </div>
</div>
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="card">
        <div class="card-header">
            <h5>Riwayat Aktivitas Sistem</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Aksi</th>
                            <th>Modul</th>
                            <th>Keterangan</th>
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
                                <td colspan="5" class="text-center">Tidak ada data log.</td>
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