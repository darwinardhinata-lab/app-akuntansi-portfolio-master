@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Master Data' => '#', 'Kronologi HPP' => null]" />
@endsection

@section('content')
<div class="container-fluid px-4">
    <h4 class="fw-bold mb-4">Kronologi HPP & Kartu Stok</h4>
    
    <form method="GET" class="mb-4 d-flex gap-2 w-50">
        <select name="product_id" class="form-select" required>
            <option value="">-- Pilih Barang --</option>
            @foreach($products as $p)
                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary fw-bold">Tampilkan</button>
    </form>

    @if($product)
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white">
            <h6 class="m-0 fw-bold">Riwayat Barang: {{ $product->name }} (HPP Saat Ini: Rp {{ number_format($product->average_cost ?? 0, 0, ',', '.') }})</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark">
                    <tr>
                        <th>Tanggal</th>
                        <th>No. Dokumen</th>
                        <th>Keterangan</th>
                        <th class="text-center text-success">Masuk (In)</th>
                        <th class="text-center text-danger">Keluar (Out)</th>
                        <th class="text-center text-primary">Saldo (Qty)</th>
                    </tr>
                </thead>
                <tbody>
                    @php $saldoQty = 0; @endphp
                    @forelse($ledgers as $l)
                        @php $saldoQty += ($l->in_qty - $l->out_qty); @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($l->transaction_date)->format('d/m/Y') }}</td>
                            <td class="fw-bold">{{ $l->document_number }}</td>
                            <td>{{ $l->description }}</td>
                            <td class="text-center text-success">{{ (float)$l->in_qty > 0 ? (float)$l->in_qty : '-' }}</td>
                            <td class="text-center text-danger">{{ (float)$l->out_qty > 0 ? (float)$l->out_qty : '-' }}</td>
                            <td class="text-center fw-bold text-primary">{{ $saldoQty }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center">Belum ada mutasi untuk barang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection