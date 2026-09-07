<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\ManufacturingProcess;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Exports\ManufacturingProcessExport;
use App\Modules\Manufacturing\Imports\ManufacturingProcessImport;
use Maatwebsite\Excel\Facades\Excel;

class ManufacturingProcessController extends Controller
{
    public function index(Request $request)
    {
        $query = ManufacturingProcess::with('defaultSupplier')->orderBy('process_name');

        if ($request->get('export') === 'excel') {
            return Excel::download(new ManufacturingProcessExport($query), 'Master_Rate_Proses_' . date('Ymd_His') . '.xlsx');
        }

        $processes = $query->paginate(50);
        $suppliers = Supplier::where('is_active', true)->orderBy('supplier_name')->get();
        return view('manufacturing.master.process_index', compact('processes', 'suppliers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'process_type' => 'required|in:KNITTING,DYEING,PRINTING,FINISHING,CUTTING,STITCHING,OTHER',
            'process_name' => 'required|string|max:150',
            'rate_unit'    => 'required|in:PER_KG,PER_PCS',
            'process_rate' => 'required|numeric|min:0',
        ]);

        ManufacturingProcess::create($request->only([
            'process_type', 'process_name', 'rate_unit', 'process_rate', 'default_supplier_id',
        ]) + ['is_active' => true]);

        SystemLog::record('CREATE', 'Manufacturing Process Master', 'Menambahkan Proses: ' . $request->process_name);
        return redirect()->back()->with('success', 'Data proses berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $process = ManufacturingProcess::findOrFail($id);
        $request->validate([
            'process_type' => 'required|in:KNITTING,DYEING,PRINTING,FINISHING,CUTTING,STITCHING,OTHER',
            'process_name' => 'required|string|max:150',
            'rate_unit'    => 'required|in:PER_KG,PER_PCS',
            'process_rate' => 'required|numeric|min:0',
        ]);

        $process->update($request->only([
            'process_type', 'process_name', 'rate_unit', 'process_rate', 'default_supplier_id',
        ]) + ['is_active' => $request->has('is_active')]);

        return redirect()->back()->with('success', 'Data proses berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $process = ManufacturingProcess::findOrFail($id);
        $process->delete();
        SystemLog::record('DELETE', 'Manufacturing Process Master', 'Menghapus Proses: ' . $process->process_name);
        return redirect()->back()->with('success', 'Data proses berhasil dihapus.');
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file_excel' => 'required|file']);
            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            Excel::import(new ManufacturingProcessImport, $request->file('file_excel'));
            SystemLog::record('IMPORT', 'Manufacturing Process Master', 'Import master rate proses dari file Excel berhasil.');
            return redirect()->back()->with('success', 'Data Master Rate Proses berhasil di-import!');
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
            ['TEMPLATE IMPORT MASTER RATE PROSES'],
            ['Pastikan format kolom tidak diubah. TIPE: KNITTING/DYEING/PRINTING/FINISHING/CUTTING/STITCHING/OTHER. SATUAN RATE: PER_KG/PER_PCS.'],
            [''], [''], [''],
            ['NAMA PROSES', 'TIPE', 'SATUAN RATE', 'RATE'],
            ['Knitting Single Jersey', 'KNITTING', 'PER_KG', '15000'],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($file, $row, ';');
            fclose($file);
        };

        return response()->streamDownload($callback, 'Template_Import_Rate_Proses.csv', ['Content-Type' => 'text/csv']);
    }
}
