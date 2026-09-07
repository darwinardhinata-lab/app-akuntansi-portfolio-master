<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Models\HelperCode;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Exports\SupplierExport;
use App\Modules\Manufacturing\Imports\SupplierImport;
use Maatwebsite\Excel\Facades\Excel;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Supplier::when($search, fn($q) => $q->where('supplier_code', 'like', "%{$search}%")
                ->orWhere('supplier_name', 'like', "%{$search}%"))
            ->orderBy('supplier_name');

        if ($request->get('export') === 'excel') {
            return Excel::download(new SupplierExport($query), 'Master_Supplier_Manufaktur_' . date('Ymd_His') . '.xlsx');
        }

        $suppliers = $query->paginate(50)->appends($request->query());
        $helperCodes = HelperCode::orderBy('entity_name')->get();
        return view('manufacturing.master.supplier_index', compact('suppliers', 'helperCodes', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_code' => 'required|string|max:50|unique:mfg_suppliers,supplier_code',
            'supplier_name' => 'required|string|max:255',
            'supplier_type' => 'required|in:RAW_MATERIAL,KNITTER,PROCESSOR,CUTTING,STITCHER,FINISHING,OTHER',
        ]);

        Supplier::create($request->only([
            'supplier_code', 'supplier_name', 'supplier_type', 'helper_code',
            'contact_person', 'phone', 'email', 'address', 'npwp',
            'payment_terms', 'credit_days',
        ]) + ['is_active' => true]);

        SystemLog::record('CREATE', 'Manufacturing Supplier Master', 'Menambahkan Supplier: ' . $request->supplier_name);
        return redirect()->back()->with('success', 'Data supplier berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $request->validate([
            'supplier_code' => 'required|string|max:50|unique:mfg_suppliers,supplier_code,' . $id,
            'supplier_name' => 'required|string|max:255',
            'supplier_type' => 'required|in:RAW_MATERIAL,KNITTER,PROCESSOR,CUTTING,STITCHER,FINISHING,OTHER',
        ]);

        $supplier->update($request->only([
            'supplier_code', 'supplier_name', 'supplier_type', 'helper_code',
            'contact_person', 'phone', 'email', 'address', 'npwp',
            'payment_terms', 'credit_days',
        ]) + ['is_active' => $request->has('is_active')]);

        return redirect()->back()->with('success', 'Data supplier berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->delete();
        SystemLog::record('DELETE', 'Manufacturing Supplier Master', 'Menghapus Supplier: ' . $supplier->supplier_name);
        return redirect()->back()->with('success', 'Data supplier berhasil dihapus.');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file_excel' => 'required|file']);
            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            Excel::import(new SupplierImport, $request->file('file_excel'));
            SystemLog::record('IMPORT', 'Manufacturing Supplier Master', 'Import master supplier dari file Excel berhasil.');
            return redirect()->back()->with('success', 'Data Master Supplier berhasil di-import!');
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errRows = [];
            foreach ($e->failures() as $failure) {
                $errRows[] = 'Baris ' . $failure->row() . ' (' . implode(', ', $failure->errors()) . ')';
            }
            return redirect()->back()->with('error', 'Gagal Import: ' . implode(' | ', array_slice($errRows, 0, 5)));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal import data: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $rows = [
            ['TEMPLATE IMPORT MASTER SUPPLIER/VENDOR MANUFAKTUR'],
            ['Pastikan format kolom tidak diubah. TIPE harus salah satu: RAW_MATERIAL, KNITTER, PROCESSOR, CUTTING, STITCHER, FINISHING, OTHER.'],
            [''], [''], [''],
            ['KODE SUPPLIER', 'NAMA', 'TIPE', 'KODE BANTU', 'KONTAK', 'TELEPON', 'EMAIL', 'ALAMAT', 'NPWP', 'TERMIN'],
            ['SUP-KNIT-01', 'CV Rajut Sejahtera', 'KNITTER', '', 'Budi', '0812xxxx', 'budi@rajut.co.id', 'Bandung', '', 'NET 30'],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($file, $row, ';');
            fclose($file);
        };

        return response()->streamDownload($callback, 'Template_Import_Supplier_Manufaktur.csv', ['Content-Type' => 'text/csv']);
    }
}
