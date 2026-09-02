<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HelperCode;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\HelperCodeImport;

class HelperCodeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $query  = HelperCode::query();

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('helper_code', 'like', '%' . $search . '%')
                  ->orWhere('entity_name', 'like', '%' . $search . '%')
                  ->orWhere('marketing_name', 'like', '%' . $search . '%');
            });
        }

        $helpers = $query->orderBy('helper_code', 'asc')
                         ->paginate(50)
                         ->appends(['search' => $search]);

        return view('helper_code.index', compact('helpers', 'search'));
    }

    public function create()
    {
        return view('helper_code.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'helper_code'    => 'required|unique:helper_codes,helper_code',
            'entity_name'    => 'required|string|max:255',
            'normal_balance' => 'required|in:DEBET,KREDIT'
        ]);

        HelperCode::create([
            'helper_code'    => strtoupper($request->helper_code),
            'entity_name'    => $request->entity_name,
            'marketing_name' => $request->marketing_name ?? '-',
            'normal_balance' => $request->normal_balance,
        ]);

        SystemLog::record('CREATE', 'Helper Code', 'Menambahkan kode bantu baru: ' . strtoupper($request->helper_code) . ' - ' . $request->entity_name);

        return redirect()->route('helper.index')->with('success', 'Kode Bantu (Relasi) berhasil ditambahkan manual!');
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $helper = HelperCode::where('helper_code', $id)->firstOrFail();
            $helper->delete();

            SystemLog::record('DELETE', 'Helper Code', 'Menghapus kode bantu: ' . $id);

            DB::commit();
            return redirect()->back()->with('success', 'Data Kode Bantu berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus: ' . $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required|string'
        ]);

        try {
            DB::beginTransaction();
            HelperCode::whereIn('helper_code', $request->ids)->delete();
            DB::commit();

            SystemLog::record('DELETE', 'Helper Code', 'Menghapus ' . count($request->ids) . ' kode bantu terpilih.');

            return redirect()->back()->with('success', count($request->ids) . ' entitas terpilih berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus massal: ' . $e->getMessage());
        }
    }

    // FIX: Sebelumnya baris Excel::import di-comment sehingga import tidak pernah benar-benar dijalankan,
    // tapi selalu return "success". Sekarang diperbaiki agar benar-benar memproses file.
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file_excel' => 'required|file'
            ]);

            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            Excel::import(new HelperCodeImport, $request->file('file_excel'));

            SystemLog::record('IMPORT', 'Helper Code', 'Import master kode bantu dari file Excel berhasil.');

            return redirect()->back()->with('success', 'Data Master Kode Bantu berhasil di-import!');

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errRows  = [];
            foreach ($failures as $failure) {
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
            ['TEMPLATE IMPORT KODE BANTU (RELASI)'],
            ['Pastikan format kolom tidak diubah.'],
            [''],
            [''],
            [''],
            ['KODE BANTU', 'NAMA ENTITAS', 'KATEGORI', 'POS SALDO NORMAL (DEBET/KREDIT)'],
            ['CUST-001', 'PT. Maju Bersama', 'Budi Santoso', 'DEBET'],
            ['SUPP-001', 'CV. Sumber Rejeki', 'Siti Aminah', 'KREDIT'],
        ];

        $callback = function() use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename=template_import_kode_bantu.csv',
        ]);
    }
}
