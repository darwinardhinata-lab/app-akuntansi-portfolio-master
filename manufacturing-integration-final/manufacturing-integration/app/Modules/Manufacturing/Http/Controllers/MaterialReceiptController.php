<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\MaterialReceipt;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Modules\Manufacturing\Exports\MaterialReceiptExport;
use App\Modules\Manufacturing\Imports\MaterialReceiptImport;
use Maatwebsite\Excel\Facades\Excel;

class MaterialReceiptController extends Controller
{
    protected MaterialReceiptService $service;

    public function __construct(MaterialReceiptService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $search = $request->get('search');

        $query = MaterialReceipt::with('supplier')
            ->when($search, fn($q) => $q->where('receipt_number', 'like', "%{$search}%"))
            ->orderBy('receipt_date', 'desc');

        if ($request->get('export') === 'excel') {
            return Excel::download(new MaterialReceiptExport($query), 'Daftar_MRN_' . date('Ymd_His') . '.xlsx');
        }

        $receipts = $query->paginate(30)->appends($request->query());
        return view('manufacturing.material_receipt.index', compact('receipts', 'search'));
    }

    public function create()
    {
        $suppliers = Supplier::where('is_active', true)->where('supplier_type', 'RAW_MATERIAL')->orderBy('supplier_name')->get();
        $openPos = MaterialPurchaseOrder::with('details')->whereIn('status', ['APPROVED', 'PARTIAL'])->orderBy('po_date', 'desc')->get();
        $yarns = Yarn::where('is_active', true)->orderBy('yarn_code')->get();
        $fabrics = Fabric::where('is_active', true)->orderBy('fabric_code')->get();

        return view('manufacturing.material_receipt.create', compact('suppliers', 'openPos', 'yarns', 'fabrics'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'receipt_date'       => 'required|date',
            'supplier_id'        => 'required|exists:mfg_suppliers,id',
            'items'               => 'required|array|min:1',
            'items.*.item_type'   => 'required|in:YARN,FABRIC',
            'items.*.qty'         => 'required|numeric|min:0.01',
            'items.*.rate'        => 'required|numeric|min:0',
            'items.*.item_name'   => 'required|string',
        ]);

        try {
            $receipt = $this->service->createAndPost(
                $request->only([
                    'receipt_date', 'supplier_id', 'po_id', 'supplier_doc_no',
                    'supplier_doc_date', 'tax_amount', 'remarks',
                ]) + ['created_by' => auth()->id()],
                $request->input('items')
            );

            SystemLog::record('CREATE', 'Manufacturing Material Receipt', 'Membuat MRN: ' . $receipt->receipt_number);
            return redirect()->route('mfg.material-receipts.show', $receipt->id)
                ->with('success', "MRN {$receipt->receipt_number} berhasil diposting. Jurnal Persediaan Bahan Baku telah digenerate.");
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', 'Gagal memposting MRN: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $receipt = MaterialReceipt::with(['supplier', 'purchaseOrder', 'details.yarn', 'details.fabric', 'journal.details'])
            ->findOrFail($id);

        return view('manufacturing.material_receipt.show', compact('receipt'));
    }

    public function void($id)
    {
        try {
            $receipt = MaterialReceipt::findOrFail($id);
            $this->service->void((int) $id);
            SystemLog::record('VOID', 'Manufacturing Material Receipt', "Void MRN: {$receipt->receipt_number}");
            return redirect()->route('mfg.material-receipts.show', $id)
                ->with('success', "MRN {$receipt->receipt_number} berhasil di-void. Jurnal & kartu stok telah dibalik.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal void MRN: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        try {
            $request->validate(['file_excel' => 'required|file']);
            $extension = strtolower($request->file('file_excel')->getClientOriginalExtension());
            if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
                return redirect()->back()->with('error', 'Gagal import: Format file harus .xlsx, .xls, atau .csv');
            }

            $import = new MaterialReceiptImport;
            Excel::import($import, $request->file('file_excel'));

            SystemLog::record('IMPORT', 'Manufacturing Material Receipt', $import->getSuccessCount() . ' MRN berhasil diimport dari Excel.');

            $message = "{$import->getSuccessCount()} MRN berhasil diimport & diposting jurnalnya.";
            if (!empty($import->getErrors())) {
                $message .= ' Namun ada ' . count($import->getErrors()) . ' baris/grup gagal: ' . implode(' | ', array_slice($import->getErrors(), 0, 5));
                return redirect()->route('mfg.material-receipts.index')->with('error', $message);
            }

            return redirect()->route('mfg.material-receipts.index')->with('success', $message);
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
            ['TEMPLATE IMPORT MRN MASSAL'],
            ['Baris dgn REF SEMENTARA yang sama akan digabung jadi 1 MRN. Nomor MRN resmi digenerate otomatis oleh sistem (bukan dari kolom ini).'],
            ['Setiap grup TETAP akan melalui validasi & posting jurnal yang sama seperti input manual (bisa gagal jika data tidak valid).'],
            [''], [''],
            ['REF SEMENTARA', 'TANGGAL (YYYY-MM-DD)', 'KODE SUPPLIER', 'NO DOKUMEN SUPPLIER', 'PAJAK', 'TIPE ITEM (YARN/FABRIC)', 'KODE ITEM', 'NAMA ITEM', 'QTY', 'SATUAN', 'RATE', 'LOT'],
            ['MRN-EXCEL-1', '2026-08-20', 'SUP-YARN-01', 'INV-0001', '0', 'YARN', 'Y-COTTON-30S', 'Cotton Combed 30s', '100', 'KGS', '45000', 'LOT-A'],
            ['MRN-EXCEL-1', '2026-08-20', 'SUP-YARN-01', 'INV-0001', '0', 'FABRIC', 'FB-GREY-180', 'Kain Grey 180gsm', '50', 'KGS', '52000', 'LOT-B'],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) fputcsv($file, $row, ';');
            fclose($file);
        };

        return response()->streamDownload($callback, 'Template_Import_MRN.csv', ['Content-Type' => 'text/csv']);
    }
}
