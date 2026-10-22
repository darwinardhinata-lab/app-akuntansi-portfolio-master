    <div data-ledger-account="{{ $accCode }}" class="card border-0 shadow-sm p-0" style="border-radius: 12px;">
        
        <div class="border-bottom p-4 bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1 text-uppercase" style="color: #1e293b;">
                    KARTU AKUN: {{ $selectedAccount->account_code ?? $accCode }} - {{ $selectedAccount?->translatedName() ?? '' }}
                </h5>
                <p class="text-muted small mb-0">{{ __('accounting.text_acfb45b8495a') }} {{ date('d M Y', strtotime($startDate)) }} s/d {{ date('d M Y', strtotime($endDate)) }}</p>
            </div>
            <div class="text-end bg-light p-2 px-3 rounded border">
                <span class="small text-muted d-block fw-bold">{{ __('erp.normal_balance_caps') }}</span>
                <span class="badge bg-dark fw-bold fs-6">{{ $isDebetNormal ? 'DEBET' : 'KREDIT' }}</span>
            </div>
        </div>

        <div class="table-responsive bg-white">
            <table class="table ledger-table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="11%" class="ps-4">{{ __('erp.date') }}</th>
                        <th width="14%">{{ __('accounting.text_9c87d44f85d1') }}</th>
                        <th width="33%">{{ __('erp.transaction_description') }}</th>
                        <th width="13%" class="text-end">{{ __('erp.debit_rp') }}</th>
                        <th width="13%" class="text-end">{{ __('erp.credit_rp') }}</th>
                        <th width="11%" class="text-end">{{ __('erp.ending_balance_rp') }}</th>
                        <th width="5%" class="text-center pe-4 no-print">{{ __('erp.journal') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light fw-bold">
                        <td colspan="3" class="ps-4 text-uppercase text-muted">
                            {{ __('erp.opening_balance_label') }} 
                            @if($page > 1) 
                                <span class="text-primary">(Pindahan ke Hal {{ $page }})</span>
                            @else 
                                <span class="text-secondary">(Sebelum {{ date('d/m/Y', strtotime($startDate)) }})</span>
                            @endif
                        </td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end text-nowrap text-primary nominal-number">{{ number_format($pageOpeningBalance, 2, ',', '.') }}</td>
                        <td class="text-center pe-4 no-print">-</td>
                    </tr>

                    @php 
                        $runningBalance = $pageOpeningBalance;
                        $totalPageDebit = 0;
                        $totalPageCredit = 0;
                    @endphp
                    @forelse($transactions as $tx)
                        @php 
                            $deb = $tx->position == 'DEBET' ? $tx->amount : 0;
                            $kre = $tx->position == 'KREDIT' ? $tx->amount : 0;
                            $traceNumber = filled($tx->evidence_number) ? $tx->evidence_number : $tx->journal_id;
                            $displayNumber = filled($tx->evidence_number) ? $tx->evidence_number : $tx->journal_id;
                            $sourceLink = $tx->header?->document_link ?? ['url' => route('jurnal.edit', $tx->journal_id), 'label' => 'Edit Jurnal', 'is_journal' => true];
                            
                            if ($isDebetNormal) {
                                $runningBalance += ($deb - $kre);
                            } else {
                                $runningBalance += ($kre - $deb);
                            }
                            
                            $totalPageDebit += $deb;
                            $totalPageCredit += $kre;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-nowrap">{{ date('d M Y', strtotime($tx->transaction_date)) }}</td>
                            <td>
                                <a href="{{ $sourceLink['url'] }}"
                                   class="text-primary text-decoration-none fw-bold"
                                   title="{{ $sourceLink['label'] }}">
                                    <i class="fa-solid fa-link fa-sm me-1"></i> {{ $displayNumber }}
                                </a>
                                @if(filled($tx->source_doc_no))
                                    <small class="d-block text-muted font-monospace mt-1">Trx: {{ $tx->source_doc_no }}</small>
                                @endif
                            </td>
                            <td class="fw-medium text-dark" title="{{ $tx->notes }}">{{ $tx->header?->display_notes ?? $tx->notes }}</td>
                            
                            <td class="text-end text-nowrap nominal-number {{ $deb > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                                {{ $deb > 0 ? number_format($deb, 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end text-nowrap nominal-number {{ $kre > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ $kre > 0 ? number_format($kre, 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end text-nowrap fw-bold text-dark nominal-number">
                                {{ number_format($runningBalance, 2, ',', '.') }}
                            </td>
                            
                            <td class="text-center pe-4 no-print">
                                @if($tx->header && $tx->header->details)
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalJournal-{{ $tx->header->journal_id ?? $tx->header->id }}" title="{{ __('erp.view_journal_pair') }}">
                                        <i class="fa-solid fa-eye small"></i>
                                    </button>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted small">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                Tidak ada transaksi mutasi pada rentang tanggal/halaman ini.
                            </td>
                        </tr>
                    @endforelse

                    {{-- Saldo adalah posisi terakhir, bukan penjumlahan saldo berjalan. --}}
                    <tr data-ledger-page-summary class="table-dark fw-bold" style="font-size: 0.9rem; break-inside: avoid; page-break-inside: avoid;">
                        <td colspan="3" class="ps-4 text-uppercase">{{ __('erp.total_balance_this_page') }}</td>
                        <td class="text-end text-nowrap nominal-number">{{ number_format($totalPageDebit, 2, ',', '.') }}</td>
                        <td class="text-end text-nowrap nominal-number">{{ number_format($totalPageCredit, 2, ',', '.') }}</td>
                        <td class="text-end text-nowrap text-warning nominal-number">{{ number_format($runningBalance, 2, ',', '.') }}</td>
                        <td class="no-print pe-4"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end no-print">
            {{ $transactions->links() }}
        </div>

    </div>
