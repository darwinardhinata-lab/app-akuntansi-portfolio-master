<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Exports\FabricExport;
use App\Modules\Manufacturing\Imports\FabricImport;
use Maatwebsite\Excel\Facades\Excel;

class FabricController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Fabric::when($search, fn($q) => $q->where('fabric_code', 'like', "%{$search}%")
                ->orWhere('fabric_type', 'like', "%{$search}%"))
            ->orderBy('fabric_code');

        if ($request->get('export') === 'excel') {
            return Excel::download(new FabricExport($query), 'Master_Fabric_' . date('Ymd_His') . '.xlsx');
        }

        $fabrics = $query->paginate(50)->appends($request->query());
        return view('manufacturing.master.fabric_index', compact('fabrics', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fabric_code' => 'required|string|max:50|unique:mfg_fabrics,fabric_code',
            'fabric_type' => 'required|string|max:50',
            'state'       => 'required|in:GREY,FINISHED',
            'unit'        => 'required|string|max:20',
        ]);

        Fabric::create($request->only([
            'fabric_code', 'fabric_type', 'subtype', 'state', 'gsm',
            'composition', 'width', 'color', 'unit',
        ]) + ['stock_quantity' => 0, 'average_cost' => 0, 'is_active' => true]);

        SystemLog::record('CREATE', 'Manufacturing Fabric Master', 'Menambahkan Fabric: ' . $request->fabric_code);
        return redirect()->back()->with('success', 'Data kain berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $fabric = Fabric::findOrFail($id);
        $request->validate([
            'fabric_code' => 'required|string|max:50|unique:mfg_fabrics,fabric_code,' . $id,
            'fabric_type' => 'required|string|max:50',
            'state'       => 'required|in:GREY,FINISHED',
            'unit'        => 'required|string|max:20',
        ]);

        $fabric->update($request->only([
            'fabric_code', 'fabric_type', 'subtype', 'state', 'gsm',
            'composition', 'width', 'color', 'unit',
        ]) + ['is_active' => $request->has('is_active')]);

        return redirect()->back()->with('success', 'Data kain berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $fabric = Fabric::findOrFail($id);
        if ((float) $fabric->stock_quantity != 0) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus kain yang masih memiliki stok.');
        }
        $fabric->delete();
        SystemLog::record('DELETE', 'Manufacturing Fabric Master', 'Menghapus Fabric: ' . $fabric->fabric_code);
        return redirect()->back()->with('success', 'Data kain berhasil dihapus.');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file_excel' => 'required|file']);
            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            Excel::import(new FabricImport, $request->file('file_excel'));
            SystemLog::record('IMPORT', 'Manufacturing Fabric Master', 'Import master fabric dari file Excel berhasil.');
            return redirect()->back()->with('success', 'Data Master Fabric berhasil di-import!');
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
            ['TEMPLATE IMPORT MASTER FABRIC'],
            ['Pastikan format kolom tidak diubah. Kolom stok & HPP TIDAK diimport lewat sini (hanya via transaksi MRN).'],
            [''], [''], [''],
            ['KODE FABRIC', 'JENIS', 'SUBTYPE', 'STATE (GREY/FINISHED)', 'GSM', 'KOMPOSISI', 'LEBAR', 'WARNA', 'SATUAN'],
            ['FB-GREY-180', 'Single Jersey', '', 'GREY', '180', '100% Cotton', '150', '', 'KGS'],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($file, $row, ';');
            fclose($file);
        };

        return response()->streamDownload($callback, 'Template_Import_Fabric.csv', ['Content-Type' => 'text/csv']);
    }
}
