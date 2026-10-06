@php
    use App\Modules\CustomsReports\Support\ReportLayout;
    use App\Modules\CustomsReports\Models\ReportPeriod;
    $columns = ReportLayout::columns($period->report_type);
    $document = in_array($period->report_type, [ReportPeriod::TYPE_PEMASUKAN, ReportPeriod::TYPE_PENGELUARAN], true);
    $incoming = $period->report_type === ReportPeriod::TYPE_PEMASUKAN;
    $customsFields = ['jenis_dok_pabean', 'no_aju', 'no_pendaftaran_dok_pabean', 'tgl_dok_pabean'];
    $proofFields = ['no_bukti', 'tgl_bukti'];
@endphp
<table class="table table-sm table-striped table-bordered align-middle mb-0" style="font-size:12px">
    <thead class="bg-light">
        <tr>
            @foreach($columns as $field => $label)
                @if($document && $field === 'jenis_dok_pabean')
                    <th colspan="{{ $incoming ? 4 : 3 }}" class="text-center">Dokumen Pabean</th>
                @elseif($document && $field === 'no_bukti')
                    <th colspan="2" class="text-center">BPB</th>
                @elseif(! $document || ! in_array($field, array_merge($customsFields, $proofFields), true))
                    <th @if($document) rowspan="2" @endif class="{{ ReportLayout::numeric($field) ? 'text-end' : 'text-center' }}">{{ $label }}</th>
                @endif
            @endforeach
        </tr>
        @if($document)
            <tr>
                @foreach($columns as $field => $label)
                    @if(in_array($field, array_merge($customsFields, $proofFields), true))
                        <th class="text-center">{{ $label }}</th>
                    @endif
                @endforeach
            </tr>
        @endif
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                @foreach($columns as $field => $label)
                    @php($value = ReportLayout::value($line, $field, $loop->parent->iteration))
                    <td class="{{ ReportLayout::numeric($field) ? 'text-end text-nowrap' : '' }}">
                        @if($value === null || $value === '')
                            —
                        @elseif(ReportLayout::numeric($field))
                            {{ number_format((float) $value, 2, ',', '.') }}
                        @else
                            {{ $value }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-3">Belum ada data.</td></tr>
        @endforelse
    </tbody>
</table>