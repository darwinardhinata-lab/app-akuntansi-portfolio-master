{{-- Partial: Tabel Dokumen Pabean (Format A - Pemasukan & Pengeluaran) --}}
<table class="table table-sm table-striped align-middle mb-0" style="font-size:12px">
    <thead class="bg-light">
        <tr>
            <th class="text-center">#</th>
            <th>Jenis Dok. Pabean</th>
            <th>No. Pendaftaran</th>
            <th class="text-center">Tgl Dok. Pabean</th>
            <th>{{ $period->noBuktiLabel() }}</th>
            <th class="text-center">Tgl Bukti</th>
            <th>{{ $period->pihakTerkaitLabel() }}</th>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th class="text-end">Jumlah</th>
            <th class="text-center">Satuan</th>
            <th class="text-center">Mata Uang</th>
            <th class="text-end">Nilai</th>
            <th>Seri FP</th>
            <th class="text-end">Nilai FP</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td>{{ $line->jenis_dok_pabean ?? '-' }}</td>
                <td>{{ $line->no_pendaftaran_dok_pabean ?? '-' }}</td>
                <td class="text-center">{{ $line->tgl_dok_pabean ? $line->tgl_dok_pabean->format('d/m/Y') : '-' }}</td>
                <td>{{ $line->no_bukti ?? '-' }}</td>
                <td class="text-center">{{ $line->tgl_bukti ? $line->tgl_bukti->format('d/m/Y') : '-' }}</td>
                <td>{{ $line->pihak_terkait ?? '-' }}</td>
                <td>{{ $line->kode_barang ?? '-' }}</td>
                <td>{{ $line->nama_barang ?? '-' }}</td>
                <td class="text-end">{{ number_format((float) $line->jumlah_barang, 2, ',', '.') }}</td>
                <td class="text-center">{{ $line->satuan_barang ?? '-' }}</td>
                <td class="text-center">{{ $line->mata_uang ?? '-' }}</td>
                <td class="text-end">{{ number_format((float) $line->nilai, 4, ',', '.') }}</td>
                <td>{{ $line->seri_faktur_pajak ?? '-' }}</td>
                <td class="text-end">{{ $line->nilai_faktur_pajak ? number_format((float) $line->nilai_faktur_pajak, 4, ',', '.') : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="15" class="text-center text-muted py-3">Belum ada data.</td></tr>
        @endforelse
    </tbody>
</table>