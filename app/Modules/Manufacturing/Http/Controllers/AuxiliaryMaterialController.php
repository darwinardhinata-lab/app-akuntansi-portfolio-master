<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Exports\AuxiliaryMaterialExport;
use App\Modules\Manufacturing\Imports\AuxiliaryMaterialImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AuxiliaryMaterialController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $query = AuxiliaryMaterial::query()
            ->when($search, fn ($q) => $q->where('material_code', 'like', "%{$search}%")->orWhere('material_name', 'like', "%{$search}%"))
            ->orderBy('material_code');

        if ($request->get('export') === 'excel') {
            return Excel::download(new AuxiliaryMaterialExport($query), 'Master_Bahan_Penolong_'.date('Ymd_His').'.xlsx');
        }

        $materials = $query->paginate(50)->appends($request->query());
        return view('manufacturing.master.auxiliary_material_index', compact('materials', 'search'));
    }

    public function import(Request $request)
    {
        $request->validate(['file_excel' => 'required|file']);
        if (! in_array(strtolower($request->file('file_excel')->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'])) return back()->with('error', 'Format file harus .xlsx, .xls, atau .csv.');
        Excel::import(new AuxiliaryMaterialImport, $request->file('file_excel'));
        return back()->with('success', 'Master Bahan Penolong berhasil diimport. Stok dan HPP tidak diubah oleh import.');
    }

    public function downloadTemplate()
    {
        $rows = [['TEMPLATE IMPORT MASTER BAHAN PENOLONG'], ['Stok dan HPP tidak diimport; keduanya hanya berubah lewat MRN/Issue.'], [], [], [], ['HS CODE', 'KODE BAHAN', 'DESCRIPTION', 'NAMA BAHAN', 'NAMA MATERIAL INGGRIS', 'KATEGORI', 'WARNA', 'SPESIFIKASI/DESKRIPSI', 'SATUAN', 'METER PER GULUNG'], ['5807100000', 'AUX-LABEL-01', 'Woven Label', 'Label Tenun', 'Woven Label', 'Bahan Penolong', 'Putih', '50x20 mm', 'PCS', '']];
        return response()->streamDownload(function () use ($rows) { $file = fopen('php://output', 'w'); foreach ($rows as $row) fputcsv($file, $row, ';'); fclose($file); }, 'Template_Import_Bahan_Penolong.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['material_code'=>'required|string|max:50|unique:mfg_auxiliary_materials,material_code','hs_code'=>'nullable|string|max:50','description'=>'nullable|string|max:255','material_name'=>'required|string|max:255','english_name'=>'nullable|string|max:255','category'=>'nullable|string|max:100','color'=>'nullable|string|max:100','specification'=>'nullable|string','meters_per_roll'=>'nullable|numeric|min:0','unit'=>'required|string|max:20']);
        AuxiliaryMaterial::create($data + ['stock_quantity'=>0,'average_cost'=>0,'inventory_account_code'=>'114004','is_active'=>true]);
        SystemLog::record('CREATE', 'Manufacturing Auxiliary Material Master', 'Menambahkan Bahan Penolong: '.$data['material_code']);
        return back()->with('success', 'Master Bahan Penolong berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $material = AuxiliaryMaterial::findOrFail($id);
        $data = $request->validate(['material_code'=>'required|string|max:50|unique:mfg_auxiliary_materials,material_code,'.$id,'hs_code'=>'nullable|string|max:50','description'=>'nullable|string|max:255','material_name'=>'required|string|max:255','english_name'=>'nullable|string|max:255','category'=>'nullable|string|max:100','color'=>'nullable|string|max:100','specification'=>'nullable|string','meters_per_roll'=>'nullable|numeric|min:0','unit'=>'required|string|max:20']);
        $material->update($data + ['is_active'=>$request->boolean('is_active')]);
        SystemLog::record('UPDATE', 'Manufacturing Auxiliary Material Master', 'Memperbarui Bahan Penolong: '.$material->material_code);
        return back()->with('success', 'Master Bahan Penolong diperbarui.');
    }

    public function destroy(int $id)
    {
        $material = AuxiliaryMaterial::findOrFail($id);
        $material->update(['is_active' => false]);
        SystemLog::record('DEACTIVATE', 'Manufacturing Auxiliary Material Master', 'Menonaktifkan Bahan Penolong: '.$material->material_code);
        return back()->with('success', 'Bahan Penolong dinonaktifkan. Riwayat issue tetap terjaga.');
    }
}