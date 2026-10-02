<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\PaymentPlan;
use App\Services\PaymentPlanCorrectionService;
use App\Support\PaymentPlanCorrectionAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentPlanCorrectionController extends Controller
{
    public function edit(Request $request, int $id)
    {
        abort_unless(PaymentPlanCorrectionAccess::allowed($request->user()), 403);
        $payment = PaymentPlan::with('details')->findOrFail($id);
        PaymentPlanCorrectionService::eligible($payment);
        $accounts = Account::orderBy('account_code')->get();

        return view('payment_plan.correction', compact('payment', 'accounts'));
    }

    public function store(Request $request, int $id, PaymentPlanCorrectionService $service)
    {
        abort_unless(PaymentPlanCorrectionAccess::allowed($request->user()), 403);
        // Reject unexpected payload fields rather than silently ignoring financial changes.
        if (array_diff(array_keys($request->all()), ['_token', 'reason', 'id_akun', 'proofs'])) {
            throw ValidationException::withMessages(['payment' => __('erp.payment_correction_fields_guard')]);
        }
        $request->merge(['reason' => trim((string) $request->input('reason', ''))]);
        $data = $request->validate([
            'reason' => 'required|string|min:10|max:1000',
            'id_akun' => 'nullable|string|exists:accounts,account_code',
            'proofs' => 'sometimes|array|min:1|max:50',
            'proofs.*' => 'required|array:id_detail,file',
            'proofs.*.id_detail' => 'required|integer|distinct',
            'proofs.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        $service->correct($id, $data);

        return redirect()->route('payment.edit', $id)->with('success', __('erp.payment_correction_success'));
    }
}
