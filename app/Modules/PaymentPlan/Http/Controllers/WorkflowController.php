<?php

namespace App\Modules\PaymentPlan\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use App\Modules\PaymentPlan\Support\ErpActor;
use Illuminate\Http\Request;

final class WorkflowController extends Controller
{
    public function workspace(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        abort_unless(config('payment_plan_v33.bank_workflow'), 404);
        $endpoints = ['data' => route('payment-plan-v33.data'), 'request' => route('payment-plan-v33.request'),
            'action' => route('payment-plan-v33.action'), 'attachment' => route('payment-plan-v33.attachment'), 'download' => url('/payment-plan/api/attachment'),
            'purchaseBills' => route('payment-plan-v33.purchase-bills'), 'print' => url('/payment-plan/bank')];
        return view('payment_plan.bank_workspace', compact('actor', 'endpoints'));
    }

    public function data(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        $domain = new \App\Modules\PaymentPlan\Domain\V33ApprovalDomain(fn () => new \DateTimeImmutable());
        $rows = \Illuminate\Support\Facades\DB::table('ppl_requests')->where('company_id', $actor['company_id'])->orderByDesc('created_at')->get();
        $requests = [];
        foreach ($rows as $row) {
            $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
            if ($domain->canSee($actor, $payload)) $requests[] = ['request' => $payload, 'version' => $row->version];
        }
        $purchaseBills = [];
        $bankBalances = [];
        $finance = (bool) array_intersect($actor['roles'], ['finance_staff', 'finance_manager', 'accountant', 'director', 'maker', 'general_manager']);
        if ($finance && config('payment_plan_v33.bank_workflow')) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($actor, &$purchaseBills, &$bankBalances) {
                $settings = (new \App\Modules\PaymentPlan\Services\BankPaymentControl())->lockContext($actor['company_id']);
                $bankBalances = $settings['_erp_projection'] ?? [];
                if (!config('payment_plan_v33.ap_allocations')) return;
                foreach (\App\Models\PurchaseBill::whereIn('payment_status', ['UNPAID', 'PARTIAL'])->orderBy('id')->limit(200)->get() as $bill) {
                    try {
                        $purchaseBills[] = (new \App\Modules\PaymentPlan\Services\PurchaseBillAllocation())->source($bill->id, $actor['company_id']);
                    } catch (RuleViolation $exception) {
                        $purchaseBills[] = ['id' => $bill->id, 'number' => $bill->bill_number, 'blocked' => $exception->rule];
                    }
                }
            });
        }
        return response()->json(compact('actor', 'requests', 'purchaseBills', 'bankBalances'));
    }

    public function purchaseBills(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        abort_unless(config('payment_plan_v33.bank_workflow') && config('payment_plan_v33.ap_allocations'), 404);
        abort_unless(array_intersect($actor['roles'], ['finance_staff', 'finance_manager', 'accountant', 'director', 'maker', 'general_manager']), 403);
        $input = $request->validate(['q' => 'nullable|string|max:100', 'page' => 'sometimes|integer|min:1']);
        return \Illuminate\Support\Facades\DB::transaction(function () use ($actor, $input) {
            (new \App\Modules\PaymentPlan\Services\BankPaymentControl())->lockContext($actor['company_id']);
            $query = \App\Models\PurchaseBill::whereIn('payment_status', ['UNPAID', 'PARTIAL']);
            if (($input['q'] ?? '') !== '') {
                $term = $input['q'];
                $query->where(fn ($q) => $q->where('bill_number', 'like', '%'.$term.'%')->orWhere('vendor_name', 'like', '%'.$term.'%'));
            }
            $page = $query->orderBy('id')->paginate(20, ['*'], 'page', $input['page'] ?? 1);
            $items = [];
            foreach ($page as $bill) {
                try {
                    $item = (new \App\Modules\PaymentPlan\Services\PurchaseBillAllocation())->source($bill->id, $actor['company_id']);
                } catch (RuleViolation $exception) {
                    $item = ['id' => $bill->id, 'number' => $bill->bill_number, 'blocked' => $exception->rule];
                }
                $items[] = $item + ['vendor' => $bill->vendor_name, 'date' => (string) $bill->bill_date, 'status' => $bill->payment_status];
            }
            return response()->json(['items' => $items, 'page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()]);
        });
    }

    public function printRequest(Request $request, string $id)
    {
        $actor = app(ErpActor::class)->resolve($request);
        abort_unless(config('payment_plan_v33.bank_workflow'), 404);
        $row = \Illuminate\Support\Facades\DB::table('ppl_requests')->where('company_id', $actor['company_id'])->where('id', $id)->first();
        abort_unless($row, 404);
        $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        abort_unless((new \App\Modules\PaymentPlan\Domain\V33ApprovalDomain(fn () => new \DateTimeImmutable()))->canSee($actor, $payload), 403);
        $events = \Illuminate\Support\Facades\DB::table('ppl_events')->where('company_id', $actor['company_id'])
            ->where('request_id', $id)->orderBy('version')->get(['event', 'actor_user_id', 'version', 'created_at']);
        return response()->view('payment_plan.bank_print', compact('payload', 'row', 'events'))
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }

    public function create(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        try {
            return response()->json(app(\App\Modules\PaymentPlan\Services\BankRequestStore::class)->create($request->all(), $actor), 201);
        } catch (RuleViolation $exception) {
            return response()->json(['error' => $exception->rule], $exception->httpStatus);
        }
    }

    public function attachment(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        $data = $request->validate(['id' => 'required|uuid', 'version' => 'required|integer|min:1', 'kind' => 'required|in:request,settlement,payment', 'file' => 'required|file|max:10240']);
        try {
            return response()->json(app(\App\Modules\PaymentPlan\Services\PrivateAttachmentStore::class)->add($data['id'], (int) $data['version'], $data['kind'], $request->file('file'), $actor));
        } catch (RuleViolation $exception) {
            return response()->json(['error' => $exception->rule], $exception->httpStatus);
        }
    }

    public function download(Request $request, string $id)
    {
        $actor = app(ErpActor::class)->resolve($request);
        $db = \Illuminate\Support\Facades\DB::class;
        $attachment = $db::table('ppl_attachments')->where('company_id', $actor['company_id'])->where('id', $id)->first();
        abort_unless($attachment && !$attachment->archived_at, 404);
        $row = $db::table('ppl_requests')->where('company_id', $actor['company_id'])->where('id', $attachment->request_id)->first();
        abort_unless($row, 404);
        $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        abort_unless((new \App\Modules\PaymentPlan\Domain\V33ApprovalDomain(fn () => new \DateTimeImmutable()))->canSee($actor, $payload), 403);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        abort_unless($disk->exists($attachment->storage_key), 404);
        abort_unless(hash_equals($attachment->sha256, hash_file('sha256', $disk->path($attachment->storage_key))), 409);
        return $disk->download($attachment->storage_key, $attachment->name, ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "sandbox; default-src 'none'"]);
    }

    public function action(Request $request)
    {
        $actor = app(ErpActor::class)->resolve($request);
        $data = $request->validate([
            'id' => 'required|uuid', 'version' => 'required|integer|min:1',
            'mutationId' => 'required|string|max:100', 'action' => 'required|string|max:50', 'input' => 'present|array',
            'input.checked' => 'sometimes|boolean', 'input.reason' => 'sometimes|nullable|string|max:2000',
            'input.reference' => 'sometimes|nullable|string|max:500',
            'input.base' => 'sometimes|decimal:0,2|min:0.01', 'input.vat' => 'sometimes|decimal:0,2|min:0',
            'input.withholding' => 'sometimes|decimal:0,2|min:0', 'input.fees' => 'sometimes|decimal:0,2|min:0',
            'input.coa' => 'sometimes|string|max:20', 'input.sourceId' => 'sometimes|string|max:20',
            'input.revisedCeiling' => 'sometimes|boolean', 'input.paidDate' => 'sometimes|date_format:Y-m-d',
            'input.result' => 'sometimes|in:pending,failed',
        ]);
        try {
            $store = new WorkflowStore(fn () => new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            return response()->json($store->action($data['id'], (int) $data['version'], $data['mutationId'], $data['action'], $data['input'] ?? [], $actor));
        } catch (RuleViolation $exception) {
            return response()->json(['error' => $exception->rule], $exception->httpStatus);
        }
    }
}