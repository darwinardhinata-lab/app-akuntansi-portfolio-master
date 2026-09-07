<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Exports\YarnExport;
use App\Modules\Manufacturing\Imports\YarnImport;
use Maatwebsite\Excel\Facades\Excel;

class YarnController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = Yarn::when($search, fn($q) => $q->where('yarn_code', 'like', "%{$search}%")
                ->orWhere('yarn_type', 'like', "%{$search}%"))
            ->orderBy('yarn_code');

        if ($request->get('export') === 'excel') {
            return Excel::download(new YarnExport($query), 'Master_Yarn_' . date('Ymd_His') . '.xlsx');
        }

        $yarns = $query->paginate(50)->appends($request->query());
        return view('manufacturing.master.yarn_index', compact('yarns', 'search'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'yarn_code' => 'required|string|max:50|unique:mfg_yarns,yarn_code',
            'yarn_type' => 'required|string|max:100',
            'unit'      => 'required|string|max:20',
        ]);

        Yarn::create($request->only([
            'yarn_code', 'yarn_type', 'yarn_count', 'composition', 'color', 'unit',
        ]) + ['stock_quantity' => 0, 'average_cost' => 0, 'is_active' => true]);

        SystemLog::record('CREATE', 'Manufacturing Yarn Master', 'Menambahkan Yarn: ' . $request->yarn_code);
        return redirect()->back()->with('success', 'Data yarn berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $yarn = Yarn::findOrFail($id);
        $request->validate([
            'yarn_code' => 'required|string|max:50|unique:mfg_yarns,yarn_code,' . $id,
            'yarn_type' => 'required|string|max:100',
            'unit'      => 'required|string|max:20',
        ]);

        $yarn->update($request->only([
            'yarn_code', 'yarn_type', 'yarn_count', 'composition', 'color', 'unit',
        ]) + ['is_active' => $request->has('is_active')]);

        return redirect()->back()->with('success', 'Data yarn berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $yarn = Yarn::findOrFail($id);
        if ((float) $yarn->stock_quantity != 0) {
            return redirect()->back()->with('error', 'Tidak bisa menghapus yarn yang masih memiliki stok.');
        }
        $yarn->delete();
        SystemLog::record('DELETE', 'Manufacturing Yarn Master', 'Menghapus Yarn: ' . $yarn->yarn_code);
        return redirect()->back()->with('success', 'Data yarn berhasil dihapus.');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file_excel' => 'required|file']);
            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            Excel::import(new YarnImport, $request->file('file_excel'));
            SystemLog::record('IMPORT', 'Manufacturing Yarn Master', 'Import master yarn dari file Excel berhasil.');
            return redirect()->back()->with('success', 'Data Master Yarn berhasil di-import!');
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
            ['TEMPLATE IMPORT MASTER YARN'],
            ['Pastikan format kolom tidak diubah. Kolom stok & HPP TIDAK diimport lewat sini (hanya via transaksi MRN).'],
            [''], [''], [''],
            ['KODE YARN', 'JENIS', 'COUNT', 'KOMPOSISI', 'WARNA', 'SATUAN'],
            ['Y-COTTON-30S', 'Cotton Combed', '30s', '100% Cotton', 'Putih', 'KGS'],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($file, $row, ';');
            fclose($file);
        };

        return response()->streamDownload($callback, 'Template_Import_Yarn.csv', ['Content-Type' => 'text/csv']);
    }
}
