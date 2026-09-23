{{-- Partial: Tabel Posisi/WIP (Format C) --}}
<table class="table table-sm table-striped align-middle mb-0" style="font-size:12px">
    <thead class="bg-light">
        <tr>
            <th class="text-center">#</th>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>Satuan</th>
            <th class="text-end">Jumlah</th>
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
                <td>{{ $line->keterangan ? \Illuminate\Support\Str::limit($line->keterangan, 60) : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr>
        @endforelse
    </tbody>
</table>