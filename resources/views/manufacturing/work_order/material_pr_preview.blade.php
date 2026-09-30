@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div><h3 class="fw-bold mb-1">Preview Material PR dari Kekurangan BOM</h3><p class="text-muted mb-0">SPK {{ $workOrder->spk_number }} — {{ $workOrder->garment_name }} · Qty rencana {{ number_format($workOrder->planned_qty) }} pcs</p></div>
        <a href="{{ route('mfg.work-orders.show', $workOrder->id) }}" class="btn btn-outline-secondary">Kembali ke SPK</a>
    </div>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <div class="alert alert-info"><i class="fa-solid fa-circle-info me-1"></i> Preview ini tidak membuat PR, tidak mengubah stok, dan tidak membuat jurnal. Saat dikonfirmasi, stok akan dihitung ulang oleh server sebelum draft PR dibuat.</div>
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-bordered table-hover align-middle mb-0"><thead class="table-light"><tr><th>Jenis</th><th>Kode</th><th>Material</th><th>Unit</th><th class="text-end">Kebutuhan BOM</th><th class="text-end">Stok Tersedia</th><th class="text-end">Kekurangan PR</th><th class="text-end">HPP Snapshot</th><th class="text-end">Estimasi Nilai PR</th><th>Status</th></tr></thead><tbody>
        @forelse($shortagePreview as $line)<tr><td>{{ $line->requirement->item_type }}</td><td>{{ $line->requirement->item_code }}</td><td>{{ $line->requirement->item_name }}</td><td>{{ $line->requirement->unit }}</td><td class="text-end">{{ number_format($line->requirement->qty_required, 6) }}</td><td class="text-end">{{ number_format($line->stock_available, 6) }}</td><td class="text-end {{ $line->shortage_qty > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' }}">{{ number_format($line->shortage_qty, 6) }}</td><td class="text-end">Rp {{ number_format($line->requirement->unit_cost_snapshot, 2) }}</td><td class="text-end">Rp {{ number_format($line->shortage_estimated_cost, 2) }}</td><td>@if(! $line->material_active)<span class="badge bg-danger">MASTER NONAKTIF</span>@elseif($line->shortage_qty > 0)<span class="badge bg-warning text-dark">PERLU PR</span>@else<span class="badge bg-success">STOK CUKUP</span>@endif</td></tr>
        @empty <tr><td colspan="10" class="text-center text-muted py-4">SPK ini belum memiliki snapshot kebutuhan BOM.</td></tr>
        @endforelse
    </tbody>@if($shortagePreview->isNotEmpty())<tfoot><tr class="fw-bold"><td colspan="6" class="text-end">Total Estimasi Kekurangan</td><td class="text-end">{{ number_format($shortagePreview->sum('shortage_qty'), 6) }}</td><td></td><td class="text-end">Rp {{ number_format($shortagePreview->sum('shortage_estimated_cost'), 2) }}</td><td></td></tr></tfoot>@endif</table></div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center"><small class="text-muted">Draft PR hanya akan berisi baris yang stoknya kurang dan master bahannya aktif.</small>@if($shortagePreview->where('shortage_qty', '>', 0)->where('material_active', true)->isNotEmpty())<form method="POST" action="{{ route('mfg.work-orders.generate-material-pr', $workOrder->id) }}" onsubmit="return confirm('Konfirmasi pembuatan draft Material PR dari kekurangan yang ditampilkan?')">@csrf<button class="btn btn-primary"><i class="fa-solid fa-cart-plus me-1"></i> Konfirmasi Buat Draft PR</button></form>@endif</div>
    </div>
</div>
@endsection