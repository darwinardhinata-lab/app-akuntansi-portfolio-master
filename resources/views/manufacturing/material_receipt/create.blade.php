@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Manufaktur' => '#', 'Material Receipt (MRN)' => route('mfg.material-receipts.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold mb-1 text-dark">Buat MRN Baru</h3>
    <p class="text-muted small mb-4">Penerimaan bahan baku dari supplier — akan langsung memposting Jurnal #1 (Debit Persediaan Bahan Baku, Kredit Hutang Usaha Maklun).</p>

    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('mfg.material-receipts.store') }}" method="POST" id="mrnForm">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Terima</label>
                        <input type="date" name="receipt_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">-- Pilih Supplier --</option>
                            @foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->supplier_code }} - {{ $s->supplier_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">No. Dokumen Supplier</label>
                        <input type="text" name="supplier_doc_no" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Pajak (Rp)</label>
                        <input type="number" step="0.01" name="tax_amount" class="form-control" value="0">
                    </div>
                </div>

                <h6 class="fw-bold mt-4 mb-2">Item Bahan Baku</h6>
                <table class="table table-bordered align-middle" id="itemsTable">
                    <thead class="table-light">
                        <tr><th>Tipe</th><th>Kode Item</th><th>Nama Item</th><th>Qty</th><th>Satuan</th><th>Rate</th><th></th></tr>
                    </thead>
                    <tbody>
                        <tr class="item-row">
                            <td>
                                <select name="items[0][item_type]" class="form-select item-type" required>
                                    <option value="YARN">YARN</option>
                                    <option value="FABRIC">FABRIC</option>
                                </select>
                            </td>
                            <td>
                                <select name="items[0][yarn_id]" class="form-select yarn-select">
                                    <option value="">-- Pilih Yarn --</option>
                                    @foreach($yarns as $y)<option value="{{ $y->id }}">{{ $y->yarn_code }}</option>@endforeach
                                </select>
                                <select name="items[0][fabric_id]" class="form-select fabric-select d-none">
                                    <option value="">-- Pilih Fabric --</option>
                                    @foreach($fabrics as $f)<option value="{{ $f->id }}">{{ $f->fabric_code }}</option>@endforeach
                                </select>
                            </td>
                            <td><input type="text" name="items[0][item_name]" class="form-control" required></td>
                            <td><input type="number" step="0.01" name="items[0][qty]" class="form-control" required></td>
                            <td><input type="text" name="items[0][unit]" class="form-control" value="KGS"></td>
                            <td><input type="number" step="0.01" name="items[0][rate]" class="form-control" required></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger remove-row">&times;</button></td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addRow"><i class="fa-solid fa-plus"></i> Tambah Item</button>

                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea name="remarks" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> Simpan & Posting Jurnal</button>
                <a href="{{ route('mfg.material-receipts.index') }}" class="btn btn-outline-secondary px-4">Batal</a>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 1;
    const tbody = document.querySelector('#itemsTable tbody');

    function toggleItemType(row) {
        const type = row.querySelector('.item-type').value;
        row.querySelector('.yarn-select').classList.toggle('d-none', type !== 'YARN');
        row.querySelector('.fabric-select').classList.toggle('d-none', type !== 'FABRIC');
    }

    document.getElementById('addRow').addEventListener('click', function () {
        const template = tbody.querySelector('.item-row').cloneNode(true);
        template.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, '[' + rowIndex + ']');
            if (el.tagName === 'INPUT') el.value = el.type === 'text' && el.classList.contains('form-control') && el.previousElementSibling === null ? '' : (el.name.includes('unit') ? 'KGS' : '');
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
        });
        template.querySelectorAll('.form-control').forEach(el => { if (el.type !== 'text' || !el.name.includes('unit')) el.value = el.name.includes('unit') ? 'KGS' : ''; });
        tbody.appendChild(template);
        toggleItemType(template);
        rowIndex++;
    });

    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('item-type')) {
            toggleItemType(e.target.closest('.item-row'));
        }
    });

    tbody.addEventListener('click', function (e) {
        if (e.target.classList.contains('remove-row') && tbody.querySelectorAll('.item-row').length > 1) {
            e.target.closest('.item-row').remove();
        }
    });
});
</script>
@endsection
