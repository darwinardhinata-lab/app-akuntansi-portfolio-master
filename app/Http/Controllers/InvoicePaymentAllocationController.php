<?php

namespace App\Http\Controllers;

use App\Services\InvoicePaymentAllocationService;
use Illuminate\Http\Request;

class InvoicePaymentAllocationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'journal_q' => 'nullable|string|max:100', 'invoice' => 'nullable|integer|min:1']);
        $invoices = \App\Models\SalesInvoice::whereNull('cancelled_at')->whereIn('payment_status', ['UNPAID', 'PARTIAL'])
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('invoice_number', 'like', '%'.$request->q.'%')->orWhere('contact_name', 'like', '%'.$request->q.'%');
            }))->orderByDesc('id')->paginate(50, ['*'], 'invoice_page')->withQueryString();
        $selectedInvoice = \App\Models\SalesInvoice::whereNull('cancelled_at')->whereIn('payment_status', ['UNPAID', 'PARTIAL'])
            ->find($request->input('invoice', $request->old('sales_invoice_id')));
        $journals = $selectedInvoice ? \App\Models\JournalHeader::where('journal_type', 'MANUAL')
            ->where('source_doc_no', $selectedInvoice->invoice_number)->where('transaction_date', '>=', $selectedInvoice->transaction_date)
            ->where('journal_id', '!=', $selectedInvoice->journal_id)
            ->whereNotIn('journal_id', \Illuminate\Support\Facades\DB::table('invoice_payment_allocations')->select('journal_id'))
            ->when($request->filled('journal_q'), fn ($query) => $query->where(function ($query) use ($request) {
                $query->where('journal_id', 'like', '%'.$request->journal_q.'%')
                    ->orWhere('evidence_number', 'like', '%'.$request->journal_q.'%');
            }))
            ->orderByDesc('transaction_date')->orderByDesc('journal_id')
            ->paginate(50, ['*'], 'journal_page')->withQueryString() : collect();
        $allocations = \Illuminate\Support\Facades\DB::table('invoice_payment_allocations as a')
            ->join('sales_invoices as i', 'i.id', '=', 'a.sales_invoice_id')
            ->leftJoin('users as u', 'u.id', '=', 'a.allocated_by')
            ->select('a.*', 'i.invoice_number', 'u.name as operator_name')
            ->orderByDesc('a.id')->paginate(50, ['*'], 'history_page')->withQueryString();
        return view('reports.invoice_payment_allocations', compact('allocations', 'invoices', 'selectedInvoice', 'journals'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_invoice_id' => 'required|integer|exists:sales_invoices,id',
            'journal_id' => 'required|string|max:100|exists:journal_headers,journal_id',
        ]);
        try {
            app(InvoicePaymentAllocationService::class)->allocate((int) $data['sales_invoice_id'], $data['journal_id'], $request->user()->id);
        } catch (\RuntimeException $e) {
            if (!$request->expectsJson()) {
                return back()->withErrors(['journal_id' => $e->getMessage()])->withInput();
            }
            return response()->json(['message' => $e->getMessage()], 422);
        }
        if (!$request->expectsJson()) {
            return redirect()->route('invoice-payment-allocations.index')->with('success', __('erp.audit_invoice_allocation_success'));
        }
        return response()->json(['message' => __('erp.audit_invoice_allocation_success')]);
    }

    public function reverse(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        try {
            app(InvoicePaymentAllocationService::class)->reverse($id, $request->user()->id, $data['reason']);
        } catch (\RuntimeException $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['reason' => $e->getMessage()]);
        }
        return $request->expectsJson() ? response()->json(['message' => __('erp.audit_ar_reversed')])
            : back()->with('success', __('erp.audit_ar_reversed'));
    }
}