<div class="table-responsive">
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <div>
            <h6 class="fw-bold mb-1 text-primary">{{ $evidence }}</h6>
            <p class="text-muted small mb-0">{{ $journals->first()->header_desc }}</p>
        </div>
        <div class="text-end">
            <span class="badge bg-light text-dark border"><i class="fa-regular fa-calendar me-1"></i> {{ date('d M Y', strtotime($journals->first()->transaction_date)) }}</span>
        </div>
    </div>
    
    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
        <thead class="table-light text-muted">
            <tr>
                <th>{{ __('erp.account_count') }}</th>
                <th class="text-end">{{ __('erp.debit_rp') }}</th>
                <th class="text-end">{{ __('erp.credit_rp') }}</th>
            </tr>
        </thead>
        <tbody>
            @php 
                $totalDebet = 0; 
                $totalKredit = 0; 
            @endphp
            @foreach($journals as $j)
                @php 
                    if($j->position == 'DEBET') $totalDebet += $j->amount;
                    else $totalKredit += $j->amount;
                @endphp
                <tr>
                    <td>
                        <div class="{{ $j->position == 'KREDIT' ? 'ms-3' : '' }}">
                            <span class="fw-bold {{ $j->position == 'DEBET' ? 'text-dark' : 'text-muted' }}">{{ $j->account_name ?? 'Akun Tidak Ditemukan' }}</span>
                            <br><small class="text-muted font-monospace">[{{ $j->account_code }}]</small>
                        </div>
                    </td>
                    <td class="text-end fw-bold {{ $j->position == 'DEBET' ? 'text-success' : 'text-muted' }}">
                        {{ $j->position == 'DEBET' ? number_format($j->amount, 2, ',', '.') : '-' }}
                    </td>
                    <td class="text-end fw-bold {{ $j->position == 'KREDIT' ? 'text-danger' : 'text-muted' }}">
                        {{ $j->position == 'KREDIT' ? number_format($j->amount, 2, ',', '.') : '-' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="table-light fw-bold">
            <tr>
                <td class="text-end text-uppercase">{{ __('erp.total') }}</td>
                <td class="text-end text-success">Rp {{ number_format($totalDebet, 2, ',', '.') }}</td>
                <td class="text-end text-danger">Rp {{ number_format($totalKredit, 2, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
