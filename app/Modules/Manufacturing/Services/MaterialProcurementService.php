<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\User;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Models\MaterialPurchaseRequest;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Support\DocumentSequence;
use App\Support\MaterialRequestAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaterialProcurementService
{
    public function createRequest(array $header, array $items): MaterialPurchaseRequest
    {
        MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canCreate(User::find($header['created_by'] ?? 0)));

        return DB::transaction(function () use ($header, $items) {
            $this->validateItems($items);
            $request = MaterialPurchaseRequest::create([
                'request_number' => DocumentSequence::generateSecure('mfg_material_purchase_requests', 'request_number', 'MPR-'.now()->format('Ymd').'-'),
                'request_date' => $header['request_date'], 'required_date' => $header['required_date'] ?? null, 'source_work_order_id' => $header['source_work_order_id'] ?? null,
                'remarks' => $header['remarks'] ?? null, 'created_by' => $header['created_by'] ?? null,
                'approval_status' => MaterialPurchaseRequest::DRAFT,
                'revision_no' => 0,
            ]);
            foreach ($items as $item) {
                $request->details()->create($this->requestDetailAttributes($item));
            }

            $this->recordRequestHistory($request, 'CREATED', null, $header['created_by']);

            return $request;
        });
    }

    public function submitRequest(int $id, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($id, $actorId) {
            $request = MaterialPurchaseRequest::with('details')->lockForUpdate()->findOrFail($id);
            MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canSubmit(User::find($actorId), $request));
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::DRAFT, 'PR hanya dapat disubmit dari DRAFT.');
            if ($request->details->isEmpty()) {
                throw new \RuntimeException('PR harus memiliki minimal satu detail.');
            }
            $request->update(['approval_status' => MaterialPurchaseRequest::SUBMITTED, 'submitted_by' => $actorId, 'submitted_at' => now()]);
            $this->recordRequestHistory($request, 'SUBMITTED', MaterialPurchaseRequest::DRAFT, $actorId);

            return $request;
        });
    }

    public function approveRequest(int $id, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($id, $actorId) {
            $request = MaterialPurchaseRequest::lockForUpdate()->findOrFail($id);
            MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canApprove(User::find($actorId), $request));
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::SUBMITTED, 'PR hanya dapat disetujui dari SUBMITTED.');
            $request->update(['approval_status' => MaterialPurchaseRequest::APPROVED, 'approved_by' => $actorId, 'approved_at' => now()]);
            $this->recordRequestHistory($request, 'APPROVED', MaterialPurchaseRequest::SUBMITTED, $actorId);

            return $request;
        });
    }

    public function rejectRequest(int $id, string $reason, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($id, $reason, $actorId) {
            $request = MaterialPurchaseRequest::lockForUpdate()->findOrFail($id);
            MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canApprove(User::find($actorId), $request));
            if (trim($reason) === '') {
                throw new \RuntimeException('Alasan penolakan PR wajib diisi.');
            }
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::SUBMITTED, 'PR hanya dapat ditolak dari SUBMITTED.');
            $request->update(['approval_status' => MaterialPurchaseRequest::REJECTED, 'rejected_by' => $actorId, 'rejected_at' => now(), 'rejection_reason' => $reason]);
            $this->recordRequestHistory($request, 'REJECTED', MaterialPurchaseRequest::SUBMITTED, $actorId, $reason);

            return $request;
        });
    }

    public function updateRequest(int $id, array $header, array $items, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($id, $header, $items, $actorId) {
            $request = MaterialPurchaseRequest::lockForUpdate()->findOrFail($id);
            MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canEdit(User::find($actorId), $request));
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::DRAFT, 'PR hanya dapat diedit dari DRAFT.');
            $this->requireUnorderedRequest($request);
            $this->validateItems($items);
            $data = array_intersect_key($header, array_flip(['request_date', 'required_date', 'remarks']));
            Validator::make($data, [
                'request_date' => 'sometimes|required|date',
                'required_date' => 'nullable|date',
                'remarks' => 'nullable|string',
            ])->validate();
            // FIX: hanya header bisnis dapat diedit; identitas, status dan nomor revisi tidak berasal dari payload.
            $request->update($data);
            $request->details()->delete();
            foreach ($items as $item) {
                $request->details()->create($this->requestDetailAttributes($item));
            }
            $this->recordRequestHistory($request, 'EDITED', MaterialPurchaseRequest::DRAFT, $actorId);

            return $request;
        });
    }

    public function reviseRequest(int $id, string $reason, ?int $actorId): MaterialPurchaseRequest
    {
        return DB::transaction(function () use ($id, $reason, $actorId) {
            $request = MaterialPurchaseRequest::lockForUpdate()->findOrFail($id);
            MaterialRequestAuthorization::ensure(MaterialRequestAuthorization::canRevise(User::find($actorId), $request));
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::REJECTED, 'PR hanya dapat direvisi dari REJECTED.');
            $this->requireUnorderedRequest($request);
            $reason = trim($reason);
            Validator::make(['reason' => $reason], ['reason' => 'required|string|min:10|max:1000'])->validate();
            // FIX: snapshot siklus lama dibersihkan; histori penolakan tetap tersimpan tanpa perubahan.
            $request->update([
                'approval_status' => MaterialPurchaseRequest::DRAFT,
                'revision_no' => $request->revision_no + 1,
                'submitted_by' => null, 'submitted_at' => null,
                'approved_by' => null, 'approved_at' => null,
                'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null,
            ]);
            $this->recordRequestHistory($request, 'REVISED', MaterialPurchaseRequest::REJECTED, $actorId, $reason);

            return $request;
        });
    }

    private function requireUnorderedRequest(MaterialPurchaseRequest $request): void
    {
        // FIX: detail dikunci bersama header sebelum pemeriksaan quantity dan penggantian detail.
        $details = $request->details()->lockForUpdate()->get();
        if ($details->contains(fn ($detail) => (float) $detail->qty_ordered > 0)) {
            throw new \RuntimeException('PR dengan quantity ordered tidak dapat diedit atau direvisi.');
        }
    }

    private function recordRequestHistory(MaterialPurchaseRequest $request, string $action, ?string $fromStatus, ?int $actorId, ?string $reason = null): void
    {
        // FIX: insert histori memakai transaksi pemanggil; kegagalan insert membatalkan mutasi PR.
        $request->histories()->create([
            'action' => $action, 'from_status' => $fromStatus, 'to_status' => $request->approval_status,
            'revision_no' => $request->revision_no, 'actor_id' => $actorId, 'reason' => $reason,
        ]);
    }

    public function createOrderFromRequest(int $requestId, int $supplierId, array $items, array $header): MaterialPurchaseOrder
    {
        return DB::transaction(function () use ($requestId, $supplierId, $items, $header) {
            $request = MaterialPurchaseRequest::with('details')->lockForUpdate()->findOrFail($requestId);
            $this->requireStatus($request->approval_status, MaterialPurchaseRequest::APPROVED, 'Material PO harus berasal dari PR APPROVED.');
            $this->validateItems($items);
            if (! Supplier::whereKey($supplierId)->where('is_active', true)->where('supplier_type', 'RAW_MATERIAL')->exists()) {
                throw new \RuntimeException('Supplier Material PO harus aktif dan bertipe RAW_MATERIAL.');
            }
            $total = 0;
            $order = MaterialPurchaseOrder::create([
                'po_number' => DocumentSequence::generateSecure('mfg_material_purchase_orders', 'po_number', 'MFGPO-'.now()->format('Ymd').'-'),
                'po_date' => $header['po_date'], 'supplier_id' => $supplierId, 'status' => 'DRAFT',
                'approval_status' => 'DRAFT', 'fulfillment_status' => 'OPEN', 'remarks' => $header['remarks'] ?? null,
                'created_by' => $header['created_by'] ?? null,
            ]);
            foreach ($items as $item) {
                $requestDetail = $request->details->firstWhere('id', (int) ($item['source_request_detail_id'] ?? 0));
                if (! $requestDetail || $requestDetail->item_type !== $item['item_type']
                    || (int) ($requestDetail->yarn_id ?? 0) !== (int) ($item['yarn_id'] ?? 0)
                    || (int) ($requestDetail->fabric_id ?? 0) !== (int) ($item['fabric_id'] ?? 0)
                    || (int) ($requestDetail->auxiliary_material_id ?? 0) !== (int) ($item['auxiliary_material_id'] ?? 0)
                    || (float) $item['qty'] > (float) $requestDetail->qty_requested - (float) $requestDetail->qty_ordered) {
                    throw new \RuntimeException('Detail Material PO harus berasal dari sisa detail PR yang disetujui.');
                }
                $amount = round((float) $item['qty'] * (float) $item['rate'], 2);
                $total += $amount;
                $order->details()->create($this->orderDetailAttributes($item) + ['source_request_detail_id' => $requestDetail->id, 'amount' => $amount]);
                $requestDetail->increment('qty_ordered', $item['qty']);
            }
            $order->update(['sub_total' => $total, 'tax_amount' => 0, 'grand_total' => $total]);

            return $order;
        });
    }

    public function submitOrder(int $id, ?int $actorId): MaterialPurchaseOrder
    {
        return DB::transaction(function () use ($id, $actorId) {
            $order = MaterialPurchaseOrder::with('details')->lockForUpdate()->findOrFail($id);
            $this->requireStatus($order->approval_status, 'DRAFT', 'Material PO hanya dapat disubmit dari DRAFT.');
            if ($order->details->isEmpty()) {
                throw new \RuntimeException('Material PO harus memiliki minimal satu detail.');
            }
            $order->update(['approval_status' => 'SUBMITTED', 'submitted_by' => $actorId, 'submitted_at' => now()]);

            return $order;
        });
    }

    public function approveOrder(int $id, ?int $actorId): MaterialPurchaseOrder
    {
        return DB::transaction(function () use ($id, $actorId) {
            $order = MaterialPurchaseOrder::lockForUpdate()->findOrFail($id);
            $this->requireStatus($order->approval_status, 'SUBMITTED', 'Material PO hanya dapat disetujui dari SUBMITTED.');
            $order->update(['approval_status' => 'APPROVED', 'approved_by' => $actorId, 'approved_at' => now(), 'status' => 'APPROVED']);

            return $order;
        });
    }

    private function requestDetailAttributes(array $item): array
    {
        return ['item_type' => $item['item_type'], 'yarn_id' => $item['item_type'] === 'YARN' ? $item['yarn_id'] : null,
            'fabric_id' => $item['item_type'] === 'FABRIC' ? $item['fabric_id'] : null, 'auxiliary_material_id' => $item['item_type'] === 'AUXILIARY' ? $item['auxiliary_material_id'] : null,
            'item_name' => $item['item_name'], 'qty_requested' => $item['qty'], 'unit' => $item['unit'] ?? 'KGS',
            'remarks' => $item['remarks'] ?? null];
    }

    private function orderDetailAttributes(array $item): array
    {
        return ['item_type' => $item['item_type'], 'yarn_id' => $item['item_type'] === 'YARN' ? $item['yarn_id'] : null,
            'fabric_id' => $item['item_type'] === 'FABRIC' ? $item['fabric_id'] : null, 'auxiliary_material_id' => $item['item_type'] === 'AUXILIARY' ? $item['auxiliary_material_id'] : null,
            'item_name' => $item['item_name'], 'qty' => $item['qty'], 'unit' => $item['unit'] ?? 'KGS',
            'rate' => $item['rate'] ?? 0];
    }

    private function validateItems(array $items): void
    {
        if (! $items) {
            throw new \RuntimeException('Dokumen harus memiliki minimal satu detail bahan baku.');
        }
        foreach ($items as $item) {
            if (! in_array($item['item_type'] ?? null, ['YARN', 'FABRIC', 'AUXILIARY'], true) || (float) ($item['qty'] ?? 0) <= 0
                || empty($item['item_name']) || (($item['item_type'] ?? null) === 'YARN' && empty($item['yarn_id']))
                || (($item['item_type'] ?? null) === 'FABRIC' && empty($item['fabric_id']))
                || (($item['item_type'] ?? null) === 'AUXILIARY' && empty($item['auxiliary_material_id']))) {
                throw new \RuntimeException('Detail bahan baku tidak valid.');
            }
            if ($item['item_type'] === 'YARN' && ! Yarn::whereKey($item['yarn_id'])->where('is_active', true)->exists()) {
                throw new \RuntimeException('Yarn harus aktif.');
            }
            if ($item['item_type'] === 'FABRIC' && ! Fabric::whereKey($item['fabric_id'])->where('is_active', true)->exists()) {
                throw new \RuntimeException('Fabric harus aktif.');
            }
            if ($item['item_type'] === 'AUXILIARY' && ! AuxiliaryMaterial::whereKey($item['auxiliary_material_id'])->where('is_active', true)->exists()) {
                throw new \RuntimeException('Bahan penolong harus aktif.');
            }
        }
    }

    private function requireStatus(string $actual, string $expected, string $message): void
    {
        if ($actual !== $expected) {
            throw new \RuntimeException($message);
        }
    }
}
