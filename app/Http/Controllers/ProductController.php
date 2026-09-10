<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\SystemLog;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $stock_status = $request->get('stock_status');
        
        $productsQuery = Product::when($search, function($q) use ($search) {
                $q->where(function($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                          ->orWhere('name', 'like', "%{$search}%")
                          ->orWhere('category_name', 'like', "%{$search}%");
                });
            })
            ->when($stock_status, function($q) use ($stock_status) {
                if ($stock_status == 'kosong') {
                    $q->where('stock_quantity', '<=', 0);
                } elseif ($stock_status == 'tersedia') {
                    $q->where('stock_quantity', '>', 0);
                }
            })
            ->orderBy('name', 'asc');

        // --- CATCH EXPORT REQUEST ---
        if ($request->get('export') === 'excel') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\ProductExport($productsQuery), 
                'Master_Barang_' . date('Ymd_His') . '.xlsx'
            );
        }

        $products = $productsQuery->paginate(50)
            ->appends(request()->query());

        return view('product.index', compact('products', 'search'));
    }

    public function create()
    {
        return view('product.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'sku'            => 'required|unique:products,sku',
            'name'           => 'required|string|max:255',
            'sell_price'     => 'required|numeric',
            'stock_quantity' => 'required|numeric'
        ]);

        Product::create([
            'sku'            => strtoupper($request->sku),
            'name'           => $request->name,
            'category_name'  => $request->category_name ?? '-',
            'variation'      => $request->variation ?? '-',
            'sell_price'     => $request->sell_price,
            'stock_quantity' => $request->stock_quantity,
        ]);

        SystemLog::record('CREATE', 'Master Barang', 'Menambahkan produk: ' . strtoupper($request->sku) . ' - ' . $request->name);

        return redirect()->route('product.index')->with('success', 'Data Master Barang berhasil ditambahkan!');
    }

    public function import(Request $request)
    {
        ini_set('max_execution_time', 1200);
        ini_set('memory_limit', '1024M'); 

        $request->validate(['file_csv' => 'required|file']);
        
        try {
            $content = file_get_contents($request->file('file_csv')->getRealPath());
            
            // Sanitasi File Mutlak
            if (!mb_check_encoding($content, 'UTF-8')) $content = mb_convert_encoding($content, 'UTF-8', 'auto');
            $content = str_replace("\x00", "", $content); 
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
            $content = preg_replace('/\r\n|\r/', "\n", $content);
            
            $lines = explode("\n", $content);
            $lines = array_map('trim', $lines);
            $lines = array_filter($lines, fn($l) => $l !== '');

            if (count($lines) < 2) return redirect()->back()->with('error', 'File CSV kosong.');

            $headerLine = array_shift($lines);
            $delimiter = substr_count($headerLine, ';') >= 5 ? ';' : ',';

            $inserted = 0;
            DB::beginTransaction();

            foreach ($lines as $line) {
                $r = str_getcsv($line, $delimiter, '"', '\\');

                $sku = trim($r[3] ?? '');
                $name = trim($r[2] ?? '');

                if (empty($sku) || empty($name) || strtolower($sku) == 'sku') continue;

                $sellPrice = preg_replace('/[^0-9\.]/', '', $r[11] ?? '0');
                $stock = preg_replace('/[^0-9\-]/', '', $r[17] ?? '0');

                Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name'           => $name,
                        'category_name'  => trim($r[4] ?? ''),
                        'variation'      => trim($r[5] ?? ''),
                        'sell_price'     => floatval($sellPrice),
                        'stock_quantity' => (int) $stock,
                    ]
                );
                $inserted++;
            }

            DB::commit();

            SystemLog::record('IMPORT', 'Master Barang', 'Import master produk berhasil. ' . $inserted . ' SKU terekam.');

            return redirect()->back()->with('success', "Import Berhasil! {$inserted} SKU Barang berhasil disinkronisasi ke Master Product.");

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal Import: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $product = Product::findOrFail($id);
        return view('product.edit', compact('product'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $request->validate([
            'sku'            => 'required|unique:products,sku,'.$id,
            'name'           => 'required|string|max:255',
            'sell_price'     => 'required|numeric',
            'stock_quantity' => 'required|numeric'
        ]);

        $product->update([
            'sku'            => strtoupper($request->sku),
            'name'           => $request->name,
            'category_name'  => $request->category_name ?? '-',
            'variation'      => $request->variation ?? '-',
            'sell_price'     => $request->sell_price,
            'stock_quantity' => $request->stock_quantity,
        ]);

        SystemLog::record('UPDATE', 'Master Barang', 'Mengubah data produk: ' . strtoupper($request->sku) . ' - ' . $request->name);

        return redirect()->route('product.index')->with('success', 'Data Barang berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        SystemLog::record('DELETE', 'Master Barang', 'Menghapus produk: ' . $product->sku . ' - ' . $product->name);
        return redirect()->route('product.index')->with('success', 'Data Barang berhasil dihapus!');
    }

    // ... kode controller sebelumnya ...

    public function downloadTemplate()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="Template_Import_Master_Barang.csv"',
        ];

        $callback = function() {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            
            // Header standar import produk (Kolom 0 sampai 17)
            fputcsv($file, [
                'Item Group', 'Group Description', 'Item Name', 'Item Code', 
                'Category', 'Keterangan/Varian', 'Merek', 'Ukuran', 'Berat', 
                'Panjang', 'Lebar', 'Sell Price', 'Purchase Price', 'Barcode', 
                'Pajak', 'Minimum Stock', 'Maximum Stock', 'Stock'
            ], ';');
            
            // Contoh Data
            fputcsv($file, [
                '', '', 'Produk Contoh', 'SKU-TEST-01', 
                'Kategori A', 'Varian Merah', '', '', '', 
                '', '', '150000', '0', '', 
                '', '', '', '50'
            ], ';');

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dispatchSyncJob(Request $request)
    {
        try {
            $forceFullSync = $request->has('force_full') && $request->force_full == '1';
            \App\Jobs\SyncProductDashboardJob::dispatch($forceFullSync);

            SystemLog::record('SYNC', 'Master Barang', 'Menjalankan sinkronisasi produk dari Dashboard ' . ($forceFullSync ? '(Full Sync)' : '(Incremental)'));

            return redirect()->back()->with('success', 'Job Sinkronisasi Produk dari Dashboard telah dijalankan di background! Mohon tunggu beberapa saat.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal memicu sinkronisasi produk: ' . $e->getMessage());
        }
    }
}
