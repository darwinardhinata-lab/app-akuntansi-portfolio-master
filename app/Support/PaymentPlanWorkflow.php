<?php

namespace App\Support;

use App\Models\Account;
use App\Models\PaymentPlan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentPlanWorkflow
{
    public static function transition(PaymentPlan $payment, string $target): void
    {
        $allowed = [
            'PENGAJUAN' => ['APPROVED', 'REJECTED'],
            'APPROVED' => ['PENGAJUAN', 'REJECTED', 'PAID'],
        ];
        if (! in_array($target, $allowed[$payment->status_payment] ?? [], true)) {
            throw ValidationException::withMessages(['status_payment' => __('erp.payment_transition_guard')]);
        }
        if ($target === 'PAID') {
            self::realized($payment);
        }
    }

    /** Manual realization evidence; not a substitute for bank reconciliation. */
    public static function realized(PaymentPlan $payment): void
    {
        PaymentFundingAccount::resolve($payment->jenis_transaksi);
        $details = $payment->details()->get();
        $valid = $payment->tgl_transaksi && $payment->jenis_transaksi
            && strtoupper($payment->jenis_transaksi) !== 'PENDING'
            && Account::where('account_code', $payment->id_akun)->exists()
            && $details->isNotEmpty();
        $total = '0.00';
        foreach ($details as $detail) {
            $amount = (string) $detail->getRawOriginal('nominal_aktual');
            $path = $detail->bukti_file;
            $valid = $valid && preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)
                && bccomp($amount ?: '0', '0', 2) > 0
                && is_string($path) && ! str_contains($path, '..')
                && ! str_contains($path, '://') && Storage::disk('public')->exists($path);
            if (preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
                $total = bcadd($total, $amount, 2);
            }
        }
        $valid = $valid && $payment->nominal_aktual !== null
            && bccomp($total, (string) $payment->nominal_aktual, 2) === 0
            && bccomp($total, (string) $payment->nominal, 2) <= 0;
        if (! $valid) {
            throw ValidationException::withMessages(['payment' => __('erp.payment_realization_guard')]);
        }
    }
}
