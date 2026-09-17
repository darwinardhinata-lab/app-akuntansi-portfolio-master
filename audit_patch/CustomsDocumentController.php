<?php

namespace App\Modules\Customs\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customs\Models\CustomsDocument;
use App\Modules\Customs\Services\CustomsDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomsDocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomsDocument::with(['details', 'statusHistory'])
            ->orderByDesc('created_at');

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $documents = $query->paginate(20);

        return view('customs.index', [
            'documents' => $documents,
            'filters' => $request->only(['document_type', 'status']),
        ]);
    }

    public function create(Request $request, string $sourceType, int $sourceId)
    {
        // FIX (T4): tambah key 'table' berisi nama tabel JAMAK. CustomsDocument::source()
        // dan test suite (CustomsDocumentFoundationTest) mengharapkan source_type berisi
        // nama tabel jamak ('purchase_orders'), bukan key route tunggal ('purchase_order').
        // Sebelumnya field ini tidak ada sehingga source_type yang tersimpan tidak pernah
        // cocok dengan mapping di CustomsDocument::source() -> resolusi dokumen sumber
        // selalu gagal (null), walau tanpa error yang kelihatan (silent bug).
        $allowedSources = [
            'purchase_order' => ['model' => \App\Models\PurchaseOrder::class, 'type' => 'PIB', 'table' => 'purchase_orders'],
            'purchase_bill' => ['model' => \App\Models\PurchaseBill::class, 'type' => 'PIB', 'table' => 'purchase_bills'],
            'sales_order' => ['model' => \App\Models\SalesOrder::class, 'type' => 'PEB', 'table' => 'sales_orders'],
            'sales_invoice' => ['model' => \App\Models\SalesInvoice::class, 'type' => 'PEB', 'table' => 'sales_invoices'],
        ];

        if (! isset($allowedSources[$sourceType])) {
            abort(404, 'Sumber dokumen tidak valid.');
        }

        $sourceConfig = $allowedSources[$sourceType];
        $sourceDocument = $sourceConfig['model']::find($sourceId);

        if (! $sourceDocument) {
            abort(404, 'Dokumen sumber tidak ditemukan.');
        }

        // FIX (T3): urutan parameter SEBELUMNYA TERTUKAR dengan signature
        // CustomsDocumentService::createDraft(string $sourceType, int $sourceId, string $documentType, ?int $createdBy).
        // Kode lama mengirim ($sourceConfig['type'], $sourceType, $sourceId, ...) — yaitu
        // 'PIB'/'PEB' ke parameter $sourceType (string, OK secara tipe tapi salah makna),
        // lalu $sourceType (string 'purchase_order') ke parameter $sourceId (int) -> TypeError
        // fatal setiap kali endpoint ini dipanggil. Root cause: argumen ditulis mengikuti urutan
        // variabel lokal di controller, bukan urutan parameter asli service.
        // Dampak finansial/operasional: fitur "buat dokumen PIB/PEB dari PO/PI/SO/Invoice"
        // 100% tidak bisa dipakai sebelum fix ini -> proses kepabeanan macet total di awal alur.
        $customsDocument = app(CustomsDocumentService::class)->createDraft(
            $sourceConfig['table'],
            $sourceId,
            $sourceConfig['type'],
            auth()->id()
        );

        return redirect()->route('customs.edit', $customsDocument->id)
            ->with('success', 'Dokumen draft berhasil dibuat.');
    }

    public function show(CustomsDocument $document)
    {
        $document->load(['details', 'statusHistory', 'logs']);

        return view('customs.show', ['document' => $document]);
    }

    public function submit(CustomsDocument $document, Request $request)
    {
        if (! Gate::allows('customs.submit')) {
            abort(403, 'Anda tidak memiliki izin untuk submit dokumen ini.');
        }

        try {
            DB::transaction(function () use ($document) {
                app(CustomsDocumentService::class)->submit($document, auth()->id());
            });

            return redirect()->route('customs.show', $document->id)
                ->with('success', 'Dokumen berhasil diajukan ke CEISA.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function retry(CustomsDocument $document, Request $request)
    {
        try {
            DB::transaction(function () use ($document) {
                app(CustomsDocumentService::class)->retry($document, auth()->id());
            });

            return redirect()->route('customs.show', $document->id)
                ->with('success', 'Retry submit berhasil dipicu.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function void(CustomsDocument $document, Request $request)
    {
        try {
            DB::transaction(function () use ($document) {
                app(CustomsDocumentService::class)->void($document, auth()->id());
            });

            return redirect()->route('customs.show', $document->id)
                ->with('success', 'Dokumen berhasil dibatalkan.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(CustomsDocument $document, Request $request)
    {
        $validated = $request->validate([
            'kode_kantor' => 'sometimes|string|max:20',
            'currency' => 'sometimes|string|size:3',
            'exchange_rate' => 'sometimes|numeric|min:0.000001',
        ]);

        try {
            DB::transaction(function () use ($document, $validated) {
                app(CustomsDocumentService::class)->updateFromPayload($document, $validated);
            });

            return back()->with('success', 'Dokumen berhasil diperbarui.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function print(CustomsDocument $document)
    {
        $document->load(['details']);
        return view('customs.print', ['document' => $document]);
    }

    public function export(Request $request)
    {
        return redirect()->route('customs.index')->with('error', 'Export belum diimplementasi.');
    }

    public function handleWebhook(Request $request)
    {
        return response()->json(['status' => 'OK']);
    }
}

