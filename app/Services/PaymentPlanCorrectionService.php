<?php

namespace App\Services;

use App\Models\PaymentPlan;
use App\Models\SystemLog;
use App\Support\PaymentPlanProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentPlanCorrectionService
{
    public function correct(int $id, array $data): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($id, $data, &$stored) {
                $payment = PaymentPlan::whereKey($id)->lockForUpdate()->firstOrFail();
                self::eligible($payment);
                $changes = [];
                if (isset($data['id_akun']) && $data['id_akun'] !== $payment->id_akun) {
                    $changes['id_akun'] = ['before' => $payment->id_akun, 'after' => $data['id_akun']];
                    $payment->id_akun = $data['id_akun'];
                }

                // Lock all selected details and verify ownership before storing any files.
                $details = $payment->details()->lockForUpdate()->get()->keyBy('id_detail');
                foreach ($data['proofs'] ?? [] as $proof) {
                    $detail = $details->get($proof['id_detail']);
                    if (! $detail || self::available($detail->bukti_file)) {
                        throw ValidationException::withMessages(['proofs' => __('erp.payment_correction_proof_guard')]);
                    }
                }
                foreach ($data['proofs'] ?? [] as $proof) {
                    $detail = $details->get($proof['id_detail']);
                    $old = $detail->bukti_file;
                    $path = $proof['file']->store('bukti_payment/corrections', 'public');
                    if (! $path) {
                        throw new \RuntimeException('Penyimpanan bukti koreksi gagal.');
                    }
                    $stored[] = $path;
                    $detail->update(['bukti_file' => $path]);
                    $changes['detail_'.$detail->id_detail] = [
                        'before' => $old, 'after' => $path,
                        'sha256' => hash_file('sha256', $proof['file']->getRealPath()),
                    ];
                }
                if (! $changes) {
                    throw ValidationException::withMessages(['payment' => __('erp.payment_correction_no_change')]);
                }
                $payment->save();
                SystemLog::record('UPDATE', 'Payment Plan', 'Koreksi terbatas PAID '.$payment->no_transaksi.' '.json_encode([
                    'reason' => $data['reason'], 'changes' => $changes,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    public static function eligible(PaymentPlan $payment): void
    {
        if ($payment->status_payment !== 'PAID' || PaymentPlanProtection::hasJournal($payment)) {
            throw ValidationException::withMessages(['payment' => __('erp.payment_correction_state_guard')]);
        }
    }

    public static function available(?string $path): bool
    {
        return $path && ! str_contains($path, '..') && ! str_contains($path, '://')
            && Storage::disk('public')->exists($path);
    }
}
