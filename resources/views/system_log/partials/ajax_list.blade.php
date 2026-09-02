@if($logs->isEmpty())
    <div class="text-center py-4 text-muted">
        <i class="fa-solid fa-clock-rotate-left fa-2x mb-2 opacity-50 d-block"></i>
        Belum ada riwayat aktivitas yang tercatat untuk data ini.
    </div>
@else
    <div class="position-relative" style="padding-left: 20px;">
        <div class="position-absolute h-100 border-start border-2 border-primary" style="left: 7px; top: 10px; opacity: 0.3;"></div>
        
        @foreach($logs as $log)
            <div class="position-relative mb-4">
                <div class="position-absolute bg-primary rounded-circle" style="width: 12px; height: 12px; left: -19px; top: 4px; border: 2px solid #fff; box-shadow: 0 0 0 2px #0d6efd;"></div>
                
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-light text-primary border border-primary-subtle fw-bold">{{ $log->action }}</span>
                    <small class="text-muted fw-bold" style="font-size: 0.75rem;"><i class="fa-regular fa-clock me-1"></i>{{ \Carbon\Carbon::parse($log->created_at)->format('d/m/Y H:i:s') }}</small>
                </div>
                <div class="fw-medium text-dark small" style="line-height: 1.4;">{{ $log->description }}</div>
                <div class="text-muted mt-1" style="font-size: 0.7rem;"><i class="fa-solid fa-user me-1"></i>Oleh: <span class="fw-bold">{{ $log->user->name ?? 'System/Robot' }}</span></div>
            </div>
        @endforeach
    </div>
@endif