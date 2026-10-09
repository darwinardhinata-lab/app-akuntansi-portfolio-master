<?php

namespace App\Support;

use App\Models\PaymentPlan;
use Illuminate\Validation\ValidationException;

class PaymentMakerChecker
{
    /** Caller holds the payment row lock and performs the workflow transition. */
    public static function approve(PaymentPlan $payment): void
    {
        $actor = auth()->id();
        if (!$actor || !$payment->maker_user_id || !$payment->last_editor_user_id
            || (int) $payment->maker_user_id === (int) $actor
            || (int) $payment->last_editor_user_id === (int) $actor) {
            throw ValidationException::withMessages(['status_payment' => __('erp.payment_maker_checker_guard')]);
        }
        $payment->forceFill(['approver_user_id' => $actor, 'approved_at' => now(),
            'approval_fingerprint' => self::fingerprint($payment)])->save();
    }

    /** Never infer original maker of a legacy record from the current editor. */
    public static function edited(PaymentPlan $payment): void
    {
        $payment->forceFill([
            'last_editor_user_id' => auth()->id(), 'approver_user_id' => null, 'approved_at' => null, 'approval_fingerprint' => null,
        ])->save();
    }

    public static function verified(PaymentPlan $payment): void
    {
        if (!$payment->maker_user_id || !$payment->last_editor_user_id || !$payment->approver_user_id || !$payment->approved_at
            || (int) $payment->approver_user_id === (int) $payment->maker_user_id
            || (int) $payment->approver_user_id === (int) $payment->last_editor_user_id
            || !$payment->approval_fingerprint || !hash_equals($payment->approval_fingerprint, self::fingerprint($payment))) {
            throw ValidationException::withMessages(['payment' => __('erp.payment_maker_checker_guard')]);
        }
    }

    public static function fingerprint(PaymentPlan $payment): string
    {
        $fields = ['id_divisi', 'id_akun', 'tgl_pengajuan', 'tgl_transaksi', 'jatuh_tempo', 'jenis_transaksi',
            'kategori_payment', 'vendor_toko', 'penerima_pj', 'rekening_va', 'keterangan', 'nominal', 'nominal_aktual',
            'qty', 'ref_bill_number', 'ref_po_number', 'maker_user_id', 'last_editor_user_id'];
        $header = [];
        foreach ($fields as $field) $header[$field] = $payment->getRawOriginal($field);
        $details = $payment->details()->orderBy('id_detail')->get()->map(function ($detail) {
            $row = [];
            foreach (['id_detail', 'nama_item', 'qty', 'harga_satuan', 'satuan', 'keterangan', 'nominal', 'nominal_aktual', 'bukti_file'] as $field) {
                $row[$field] = $detail->getRawOriginal($field);
            }
            return $row;
        })->all();
        return hash('sha256', json_encode([$header, $details], JSON_THROW_ON_ERROR));
    }
}