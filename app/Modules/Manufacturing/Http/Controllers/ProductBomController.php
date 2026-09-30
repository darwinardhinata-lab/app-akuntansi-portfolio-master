<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\ProductBom;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\ProductBomService;
use App\Modules\Manufacturing\Exports\ProductBomExport;
use App\Modules\Manufacturing\Imports\ProductBomImport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductBomController extends Controller
{
    public function index(Request $request)
    {
        if ($request->get('export') === 'excel') return Excel::download(new ProductBomExport(), 'BOM_Produk_'.now()->format('Ymd_His').'.xlsx');
        $search = $request->string('search')->toString();
        $products = Product::query()
            ->withCount(['manufacturingBoms as active_bom_count' => fn ($query) => $query->where('is_active', true)])
            ->when($search, fn ($query) => $query->where(fn ($q) => $q->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(50)
            ->appends($request->query());

        return view('manufacturing.bom.index', compact('products', 'search'));
    }

    public function import(Request $request)
    {
        $request->validate(['file_excel' => 'required|file']);
        if (! in_array(strtolower($request->file('file_excel')->getClientOriginalExtension()), ['xlsx', 'xls', 'csv'], true)) return back()->with('error', 'Format file harus .xlsx, .xls, atau .csv.');
        try {
            Excel::import(new ProductBomImport(), $request->file('file_excel'));
            return back()->with('success', 'BOM berhasil diimport. Baris dengan produk dan bahan yang sama diperbarui, tidak diduplikasi.');
        } catch (\Throwable $exception) {
            return back()->with('error', 'Gagal import BOM: '.$exception->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $rows = [['TEMPLATE IMPORT BOM PRODUK'], ['Import BOM tidak mengubah stok, HPP, jurnal, atau kartu stok.'], [], [], [], ['SKU BARANG JADI', 'NAMA BARANG JADI', 'JENIS BAHAN (YARN/FABRIC/AUXILIARY)', 'KODE BAHAN', 'QTY PER UNIT', 'WASTE %', 'CATATAN', 'STATUS (AKTIF/NONAKTIF)'], ['BAG-600D-01', 'Tas Polyester 600D', 'FABRIC', 'A.001014', '0.058', '2.5', 'Bahan utama body tas', 'AKTIF']];
        return response()->streamDownload(function () use ($rows) { $file = fopen('php://output', 'w'); foreach ($rows as $row) fputcsv($file, $row, ';'); fclose($file); }, 'Template_Import_BOM_Produk.csv', ['Content-Type' => 'text/csv']);
    }

    public function show(int $productId)
    {
        $product = Product::findOrFail($productId);
        $boms = ProductBom::where('product_id', $productId)->orderBy('item_type')->get();
        return view('manufacturing.bom.show', [
            'product' => $product, 'boms' => $boms,
            'yarns' => Yarn::where('is_active', true)->orderBy('yarn_code')->get(),
            'fabrics' => Fabric::where('is_active', true)->orderBy('fabric_code')->get(),
            'auxiliaryMaterials' => AuxiliaryMaterial::where('is_active', true)->orderBy('material_code')->get(),
        ]);
    }

    public function store(Request $request, int $productId, ProductBomService $service)
    {
        Product::findOrFail($productId);
        $data = $request->validate([
            'item_type' => ['required', Rule::in(['YARN', 'FABRIC', 'AUXILIARY'])],
            'material_id' => 'required|integer|min:1', 'qty_per_unit' => 'required|numeric|gt:0',
            'waste_percent' => 'nullable|numeric|min:0|max:100', 'remarks' => 'nullable|string',
        ]);
        try {
            $service->save($productId, $data);
            return back()->with('success', 'Item BOM berhasil ditambahkan.');
        } catch (\Throwable $exception) {
            return back()->withInput()->with('error', 'Gagal menyimpan BOM: '.$exception->getMessage());
        }
    }

    public function destroy(int $productId, int $id)
    {
        ProductBom::where('product_id', $productId)->findOrFail($id)->delete();
        return back()->with('success', 'Item BOM berhasil dihapus. Snapshot pada SPK yang sudah dibuat tidak berubah.');
    }
}