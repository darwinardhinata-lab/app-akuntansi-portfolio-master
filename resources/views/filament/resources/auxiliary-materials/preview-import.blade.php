<x-filament-panels::page>
    <p>Preview tidak menyimpan data. Simpan hanya membuat master baru dan tidak mengubah stok, HPP, atau jurnal.</p>
    <p>Unit yang diterima: <strong>PCS</strong> (pieces), persis tanpa konversi. Teks CSV yang ditempel hanya untuk preview. Untuk XLSX, isi kode dan meter per gulung sebagai teks agar nilainya tetap eksak.</p>
    <p>Tempel isi CSV UTF-8 atau unggah file (.xlsx / .csv) dari template standar Bahan Penolong existing (pemisah titik koma, header baris 6, data mulai baris 7, 10 kolom). Maksimum input teks 1.048.576 karakter atau file 5 MB. Format legacy dan pemetaan sumber lain belum didukung di preview ini.</p>
    <p>Kode yang sudah ada atau berulang ditolak; tidak ada merge. Saat menyimpan, server memeriksa ulang file dan konflik kode menurut database.</p>
    <p>Batas file masuk 5 MiB (5.242.880 byte). Seluruh hasil file yang diterima ditampilkan, tanpa pemotongan: maksimum 500 record data termasuk record kosong, 20 kolom (10 kolom template; sisanya belum dipetakan), 4.096 karakter UTF-8 per nilai, dan 262.144 byte JSON hasil sebelum penandaan duplikat. Nomor baris CSV adalah nomor record, bukan baris fisik jika teks mengandung newline. Bagi file jika batas terlampaui.</p>
    <p>XLSX harus tepat satu worksheet; batas isi arsip setelah ekstraksi 8.388.608 byte, 128 entri, 100.000 elemen XML per bagian, dan 10.120 sel; baris worksheet maksimum 506 termasuk template. XLS/TXT tidak didukung pada preview ini.</p>
    <form wire:submit="preview">
        <div class="mb-4">
            <label for="preview-file" class="block font-medium mb-1">Unggah file template (.xlsx / .csv)</label>
            <input type="file" id="preview-file" wire:model="file" class="w-full rounded border p-2" accept=".xlsx,.csv" aria-describedby="preview-file-error">
            <div id="preview-file-error">@error('file')<p role="alert">{{ $message }}</p>@enderror</div>
        </div>
        <div class="mb-4">
            <label for="preview-csv" class="block font-medium mb-1">Atau tempel isi CSV template Bahan Penolong</label>
            <textarea id="preview-csv" wire:model="csv" rows="8" class="w-full rounded border p-2" aria-describedby="preview-error"></textarea>
            <div id="preview-error">@error('csv')<p role="alert">{{ $message }}</p>@enderror</div>
        </div>
        <x-filament::button type="submit" wire:loading.attr="disabled">Validasi preview (tanpa simpan)</x-filament::button>
    </form>
    @if($saved)
        <p role="status">{{ $saved }} bahan penolong baru berhasil disimpan.</p>
        <a href="{{ \App\Filament\Resources\AuxiliaryMaterialResource::getUrl() }}">Kembali ke lookup Bahan Penolong</a>
    @endif
    @error('save')<p role="alert">{{ $message }}</p>@enderror
    @if($inspected)
        <p role="status">{{ count($rows) }} baris data diperiksa (sumber: {{ $sourceType === 'file' ? 'File Unggahan' : 'Teks CSV' }}). Tidak ada data bisnis disimpan.</p>
        @if($this->canSave())
            <x-filament::button type="button" wire:click="save" wire:loading.attr="disabled">Simpan</x-filament::button>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr><th>Baris File</th><th>Kode / nama</th><th>Nilai sumber terpetakan</th><th>Validasi</th><th>Calon duplikat</th><th>Belum dipetakan</th></tr></thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td>{{ $row['row'] }}</td>
                            <td>{{ $row['data']['material_code'] }} / {{ $row['data']['material_name'] }}</td>
                            <td>@foreach($row['data'] as $field => $value)<div>{{ $field }}: {{ $value }}</div>@endforeach</td>
                            <td>@forelse($row['errors'] as $error)<div>{{ $error }}</div>@empty Lulus validasi field @endforelse</td>
                            <td>{{ $row['existing_duplicate'] ? 'Kode sudah ada di master. ' : '' }}{{ $row['file_duplicate'] ? 'Kode berulang di file.' : '' }}</td>
                            <td>@foreach($row['unmapped'] as $field => $value)<div>{{ $field }}: {{ $value }}</div>@endforeach</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
