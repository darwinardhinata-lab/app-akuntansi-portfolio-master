{{-- Partial: Tabel Mutasi (Format B - Bahan Baku, Barang Jadi, Modal, Reject) --}}
<table class="table table-sm table-striped align-middle mb-0" style="font-size:12px">
    <thead class="bg-light">
        <tr>
            <th class="text-center">#</th>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Satuan</th>
            <th class="text-end">Jumlah</th>
            <th class="text-end">Saldo Awal</th>
            <th class="text-end">Pemasukan</th>
            <th class="text-end">Pengeluaran</th>
            <th class="text-end">Adjustment</th>
            <th class="text-end">Saldo Akhir</th>
            <th class="text-center">Hasil Cacah</th>
            <th class="text-end">Selisih</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lines as $line)
            <tr>
                <td class="text-center">{{ $loop->iteration }}</td>
                <td>{{ $line->kode_barang ?? '-' }}</td>
                <td>{{ $line->nama_barang ?? '-' }}</td>
                <td>{{ $line->satuan_barang ?? '-' }}</td>
                <td class="text-end">{{ number_format((float) $line->jumlah_barang, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $line->saldo_awal, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $line->jumlah_pemasukan_barang, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $line->jumlah_pengeluaran_barang, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $line->penyesuaian_adjustment, 2, ',', '.') }}</td>
                <td class="text-end">{{ number_format((float) $line->saldo_akhir, 2, ',', '.') }}</td>
                <td class="text-center">{{ $line->hasil_pencacahan ?? '-' }}</td>
                <td class="text-end">{{ number_format((float) $line->jumlah_selisih, 2, ',', '.') }}</td>
                <td>{{ $line->keterangan ? \Illuminate\Support\Str::limit($line->keterangan, 50) : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="text-center text-muted py-3">Belum ada data.</td></tr>
        @endforelse
    </tbody>
</table>