<?php

namespace App\Modules\PaymentPlan\Services;

use App\Models\PurchaseBill;
use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Support\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ApAllocationService
{
    private const EVENT_ALLOCATE = 'ap.allocation.created';
    private const EVENT_REVERSE = 'ap.allocation.reversed';
    private const EVENT_WAIVE = 'ap.match.waived';
    private const EVENT_MATCH = 'ap.match.resolved';

    public function validateThreeWayMatch(int $invoiceId, int $companyId): array
    {
        $bill = PurchaseBill::whereKey($invoiceId)
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->firstOrFail();

        $exceptions = [];
        $matched = true;

        if (!$bill->purchase_order_id) {
            $matched = false;
            $exceptions[] = ['source' => 'purchase_bill:' . $invoiceId, 'reason' => 'no_purchase_order_linked'];
        }

        if ($bill->purchase_order_id) {
            $grnCount = DB::table('purchase_receipts')
                ->where('purchase_order_id', $bill->purchase_order_id)
                ->whereIn('status', ['COMPLETED', 'RECEIVED'])
                ->count();
            if ($grnCount === 0) {
                $matched = false;
                $exceptions[] = ['source' => 'purchase_order:' . $bill->purchase_order_id, 'reason' => 'no_completed_grn'];
            }
        }

        if (!$bill->journal_id) {
            $matched = false;
            $exceptions[] = ['source' => 'purchase_bill:' . $invoiceId, 'reason' => 'no_posted_journal'];
        } else {
            $journal = DB::table('journal_headers')
                ->where('journal_id', $bill->journal_id)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();
            if (!$journal) {
                $matched = false;
                $exceptions[] = ['source' => 'journal:' . $bill->journal_id, 'reason' => 'journal_not_found'];
            } elseif ($journal->currency_code !== 'IDR') {
                $matched = false;
                $exceptions[] = ['source' => 'journal:' . $bill->journal_id, 'reason' => 'non_idr_currency_not_supported'];
            }
        }

        if ($matched && $bill->purchase_order_id) {
            $toleranceQty = (string) ($bill->match_tolerance_qty ?? '0.00');
            $tolerancePrice = (string) ($bill->match_tolerance_price ?? '0.00');

            $poLines = DB::table('purchase_order_details')
                ->where('purchase_order_id', $bill->purchase_order_id)
                ->get();
            $grnLines = DB::table('purchase_receipt_details')
                ->join('purchase_receipts', 'purchase_receipt_details.purchase_receipt_id', '=', 'purchase_receipts.id')
                ->where('purchase_receipts.purchase_order_id', $bill->purchase_order_id)
                ->whereIn('purchase_receipts.status', ['COMPLETED', 'RECEIVED'])
                ->get();

            foreach ($poLines as $poLine) {
                $grnLine = $grnLines->first(fn ($l) => $l->item_code === ($poLine->item_code ?? null));

    public function postAllocationJournal(int $allocationId, int $companyId, User $actor): string
    {
        abort_unless(config('platform.posting_intents_enabled', false), 409, 'Accounting posting intents are disabled.');
        abort_unless($actor->exists && $actor->role === 'accountant', 403, 'Accountant required for journal posting.');

        return DB::transaction(function () use ($allocationId, $companyId, $actor) {
            $allocation = DB::table('ppl_ap_payment_allocations')
                ->where('id', $allocationId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($allocation->journal_id) {
                throw new RuleViolation('allocation_already_posted', 409);
            }

            $bill = PurchaseBill::whereKey((int) $allocation->invoice_id)->lockForUpdate()->firstOrFail();
            $amount = (string) $allocation->allocated_amount;

            // Get AP credit account from bill
            $apAccount = $bill->credit_account;
            abort_unless($apAccount, 409, 'No AP account on invoice.');

            // Get bank account - use company's default IDR payment account
            $bankAccount = config('payment_plan_v33.payment_accounts.idr3', '111003');

            $idempotencyKey = 'ppl:ap:post:' . $companyId . ':' . $allocationId;
            $intent = [
                'idempotency_key' => $idempotencyKey,
                'source_type' => 'AP_PAYMENT_ALLOCATION',
                'source_id' => (string) $allocationId,
                'actor_id' => (string) $actor->getKey(),
            ];

            $header = PostingService::post(
                [
                    'transaction_date' => now()->toDateString(),
                    'journal_type' => 'AP_PAYMENT',
                    'source_doc_no' => 'ALLOC-' . $allocationId,
                    'notes' => 'AP payment allocation for invoice #' . $allocation->invoice_id,
                    'currency_code' => 'IDR',
                    'exchange_rate' => '1.000000',
                ],
                [
                    ['account_code' => $apAccount, 'position' => 'DEBET', 'amount' => $amount],
                    ['account_code' => $bankAccount, 'position' => 'KREDIT', 'amount' => $amount],
                ],
                null,
                null,
                $intent
            );

            // Link journal to allocation
            DB::table('ppl_ap_payment_allocations')->where('id', $allocationId)->update([
                'journal_id' => $header->journal_id,
                'updated_at' => now(),
            ]);

            // Update bill status if fully allocated
            $outstanding = $this->calculateOutstanding((int) $allocation->invoice_id, $companyId);
            if (bccomp($outstanding, '0.00', 2) <= 0) {
                PurchaseBill::whereKey((int) $allocation->invoice_id)->update([
                    'payment_status' => 'PAID',
                ]);
            }

            return $header->journal_id;
        });
    }

                if (!$grnLine) {
                    $matched = false;
                    $exceptions[] = ['source' => 'po_line:' . $poLine->id, 'reason' => 'no_matching_grn_line'];
                    continue;
                }
                $qtyDiff = bccomp((string) $poLine->quantity, (string) $grnLine->quantity_received, 6);
                if (bccomp(abs((string) $qtyDiff), $toleranceQty, 6) > 0) {
                    $matched = false;
                    $exceptions[] = [
                        'source' => 'po_line:' . $poLine->id,
                        'reason' => 'qty_tolerance_exceeded',
                        'po_qty' => (string) $poLine->quantity,
                        'grn_qty' => (string) $grnLine->quantity_received,
                        'tolerance' => $toleranceQty,
                    ];
                }
                $priceDiff = bccomp((string) $poLine->unit_price, (string) $grnLine->unit_price, 2);
                if (bccomp(abs((string) $priceDiff), $tolerancePrice, 2) > 0) {
                    $matched = false;
                    $exceptions[] = [
                        'source' => 'po_line:' . $poLine->id,
                        'reason' => 'price_tolerance_exceeded',
                        'po_price' => (string) $poLine->unit_price,
                        'grn_price' => (string) $grnLine->unit_price,
                        'tolerance' => $tolerancePrice,
                    ];
                }
            }
        }

        $status = $matched ? 'matched' : 'exception';

        DB::table('ppl_events')->insert([
            'company_id' => $companyId,
            'request_id' => null,
            'actor_user_id' => null,
            'mutation_id' => 'ap:match:' . $invoiceId . ':' . now()->timestamp,
            'payload_hash' => hash('sha256', json_encode(['invoice_id' => $invoiceId, 'status' => $status], JSON_THROW_ON_ERROR)),
            'event' => self::EVENT_MATCH,
            'version' => 1,
            'result' => json_encode(['matched' => $matched, 'exceptions' => $exceptions], JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return [
            'match_status' => $status,
            'exceptions' => $exceptions,
            'matched' => $matched,
        ];
    }


    public function allocatePayment(array $data): array
    {
        $validator = Validator::make($data, [
            'company_id' => 'required|integer|exists:companies,id',
            'actor_user_id' => 'required|integer|exists:users,id',
            'mutation_id' => 'required|string',
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|integer|exists:purchase_bills,id',
            'allocations.*.amount' => 'required|decimal:0,2|min:0.01',
        ]);

        if ($validator->fails()) {
            throw new RuleViolation('validation_failed', 422, $validator->errors()->toArray());
        }

        $companyId = (int) $data['company_id'];
        $actorUserId = (int) $data['actor_user_id'];
        $mutationId = $data['mutation_id'];
        $allocations = $data['allocations'];

        return DB::transaction(function () use ($companyId, $actorUserId, $mutationId, $allocations) {
            // Acquire financial fence to serialize allocation
            $fence = app(\App\Modules\PaymentPlan\Domain\LegacyFinancialFence::class);
            $fence->lock(true);

            $totalAllocated = '0.00';
            $createdAllocations = [];

            foreach ($allocations as $alloc) {
                $invoiceId = (int) $alloc['invoice_id'];
                $amount = (string) $alloc['amount'];

                $bill = PurchaseBill::whereKey($invoiceId)
                    ->where('company_id', $companyId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Validation: invoice must be posted/approved
                if (!$bill->journal_id) {
                    throw new RuleViolation('invoice_not_posted', 409);
                }

                // Validation: match must be matched or waived
                if (!in_array($bill->match_status, ['matched', 'waived'], true)) {
                    throw new RuleViolation('three_way_match_required', 409);
                }

                // Validation: outstanding check
                $outstanding = $this->calculateOutstanding($invoiceId, $companyId);
                if (bccomp($amount, $outstanding, 2) > 0) {
                    throw new RuleViolation('allocation_exceeds_outstanding', 409);
                }

                // Currency check: invoice must be IDR (R2 no auto-FX)
                if (($bill->journal_id && DB::table('journal_headers')->where('journal_id', $bill->journal_id)->value('currency_code') !== 'IDR')) {
                    throw new RuleViolation('non_idr_invoice_not_supported', 409);
                }

                // Idempotency: check if this exact allocation already exists
                $idempotencyKey = 'ppl:ap:alloc:' . $companyId . ':' . $invoiceId . ':' . $amount . ':' . $mutationId;
                $existing = DB::table('ppl_ap_payment_allocations')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    throw new RuleViolation('duplicate_allocation', 409);
                }

                // Create allocation record
                $allocationId = DB::table('ppl_ap_payment_allocations')->insertGetId([
                    'company_id' => $companyId,
                    'payment_request_id' => null,
                    'invoice_id' => $invoiceId,
                    'allocated_amount' => $amount,
                    'currency' => 'IDR',
                    'allocated_at' => now(),
                    'journal_id' => null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update bill outstanding tracking
                $newAllocated = bcadd((string) $bill->allocated_amount, $amount, 2);
                $newOutstanding = bcsub((string) $bill->grand_total, $newAllocated, 2);
                $newCount = (int) $bill->payment_allocation_count + 1;

                PurchaseBill::whereKey($invoiceId)->update([
                    'allocated_amount' => $newAllocated,
                    'outstanding_amount' => $newOutstanding,
                    'payment_allocation_count' => $newCount,
                ]);

                // Emit allocation event
                DB::table('ppl_events')->insert([
                    'company_id' => $companyId,
                    'request_id' => null,
                    'actor_user_id' => $actorUserId,
                    'mutation_id' => $mutationId,
                    'payload_hash' => hash('sha256', json_encode(['invoice_id' => $invoiceId, 'amount' => $amount], JSON_THROW_ON_ERROR)),
                    'event' => self::EVENT_ALLOCATE,
                    'version' => 1,
                    'result' => json_encode(['allocation_id' => $allocationId, 'invoice_id' => $invoiceId, 'amount' => $amount], JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                ]);

                $totalAllocated = bcadd($totalAllocated, $amount, 2);
                $createdAllocations[] = [
                    'allocation_id' => $allocationId,
                    'invoice_id' => $invoiceId,
                    'allocated_amount' => $amount,
                    'outstanding_after' => $newOutstanding,
                ];
            }

            return [
                'allocations' => $createdAllocations,
                'total_allocated' => $totalAllocated,
            ];
        });
    }
