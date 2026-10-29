<?php

namespace App\Modules\PaymentPlan\Services;

use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Domain\V33ApprovalDomain;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class PrivateAttachmentStore
{
    public function reconciliation(int $id, int $version, ?UploadedFile $file = null, ?string $archive = null): void
    {
        $key = null;
        try {
            DB::transaction(function () use ($id, $version, $file, $archive, &$key) {
                $company = $this->reconciliationCompany($archive ? 'cancel' : 'propose');
                LegacyFinancialFence::lock(true);
                $row = DB::table('ppl_ap_reconciliations')->where('company_id', $company)->where('id', $id)->lockForUpdate()->first();
                abort_unless($row, 404);
                abort_unless($row->status === 'proposed' && (int) $row->proposed_by === (int) auth()->id(), 403);
                abort_unless($row->version === $version, 409);
                if ($archive) {
                    $attachment = DB::table('ppl_attachments')->where('reconciliation_id', $id)->where('id', $archive)->whereNull('archived_at')->first();
                    abort_unless($attachment, 404);
                    DB::table('ppl_attachments')->where('id', $archive)->update(['archived_at' => now()]);
                    $this->audit($archive, 'archive');
                } else {
                    abort_unless($file && $file->isValid() && $file->getSize() <= 10 * 1024 * 1024
                        && in_array($file->getMimeType(), ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true), 422);
                    abort_if(DB::table('ppl_attachments')->where('reconciliation_id', $id)->count() >= 40, 422);
                    $hash = hash_file('sha256', $file->getRealPath());
                    abort_if(DB::table('ppl_attachments')->where('reconciliation_id', $id)->where('sha256', $hash)->exists(), 409);
                    $uuid = (string) Str::uuid();
                    $key = 'payment-plan/'.$company.'/reconciliation/'.$id.'/'.$uuid;
                    if (!Storage::disk('local')->put($key, file_get_contents($file->getRealPath()))) throw new \RuntimeException('Private attachment write failed.');
                    DB::table('ppl_attachments')->insert(['id' => $uuid, 'request_id' => null, 'reconciliation_id' => $id,
                        'company_id' => $company, 'uploader_id' => auth()->id(), 'kind' => 'reconciliation', 'name' => basename($file->getClientOriginalName()),
                        'mime' => $file->getMimeType(), 'sha256' => $hash, 'storage_key' => $key, 'created_at' => now()]);
                    $this->audit($uuid, 'upload');
                }
                DB::table('ppl_ap_reconciliations')->where('id', $id)->update(['version' => $version + 1, 'updated_at' => now()]);
            });
        } catch (\Throwable $e) {
            if ($key) Storage::disk('local')->delete($key);
            throw $e;
        }
    }

    public function reconciliationCompany(string $action = 'view'): int
    {
        abort_unless(config('payment_plan_v33.bank_workflow') && app(\App\Support\ErpPanelAccess::class)->allows(request(), auth()->user(), 'ap.reconcile.'.$action), 403);
        return (int) app(\App\Modules\Platform\Support\CompanyContext::class)->requireCompany(request())->id;
    }

    /** Archived evidence is retained and may be read only by a separately granted audit reviewer. */
    public function archivedReconciliationCompany(): int
    {
        return $this->reconciliationCompany('audit');
    }

    public function audit(string $id, string $action): void
    {
        DB::table('ppl_attachment_audits')->insert(['attachment_id' => $id, 'actor_id' => auth()->id(), 'action' => $action, 'created_at' => now()]);
    }

    public function response(object $attachment, bool $preview = false, bool $archivedReview = false)
    {
        // Defense in depth for callers other than the two attachment workflows.
        abort_unless(($attachment->request_id === null) xor ($attachment->reconciliation_id === null), 409);
        abort_if($attachment->archived_at && !$archivedReview, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($attachment->storage_key), 404);
        abort_unless(hash_equals($attachment->sha256, hash_file('sha256', $disk->path($attachment->storage_key))), 409);
        $this->audit($attachment->id, $archivedReview ? ($preview ? 'archived_preview' : 'archived_download') : ($preview ? 'preview' : 'download'));
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Type' => $attachment->mime,
            'Content-Security-Policy' => "sandbox; default-src 'none'"];
        return $preview ? $disk->response($attachment->storage_key, $attachment->name, $headers) : $disk->download($attachment->storage_key, $attachment->name, $headers);
    }

    public function add(string $requestId, int $version, string $kind, UploadedFile $file, array $actor): array
    {
        if (!config('payment_plan_v33.enabled') || !config('payment_plan_v33.bank_workflow')) throw new RuleViolation('disabled', 404);
        if (!$file->isValid() || $file->getSize() > 10 * 1024 * 1024 || !in_array($file->getMimeType(), ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'], true)) throw new RuleViolation('invalid_attachment');
        $key = null;
        try {
            return DB::transaction(function () use ($requestId, $version, $kind, $file, $actor, &$key) {
                (new BankPaymentControl())->lockContext($actor['company_id']);
                $row = DB::table('ppl_requests')->where('company_id', $actor['company_id'])->where('id', $requestId)->lockForUpdate()->first();
                if (!$row) throw new RuleViolation('not_found', 404);
                $payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                if (!(new V33ApprovalDomain(fn () => new \DateTimeImmutable()))->canSee($actor, $payload)) throw new RuleViolation('forbidden', 403);
                $finance = (bool) array_intersect($actor['roles'], ['finance_staff', 'finance_manager']);
                $allowed = match ($kind) {
                    'request' => $payload['status'] === 'draft' && $payload['createdBy'] === $actor['id'],
                    'settlement' => $payload['status'] === 'request_approved' && $finance,
                    'payment' => in_array($payload['status'], ['bank_released', 'bank_hold'], true) && $finance,
                    default => false,
                };
                if (!$allowed) throw new RuleViolation('attachment_stage_denied', 403);
                if ($version !== $row->version) throw new RuleViolation('stale_version', 409);
                if (DB::table('ppl_attachments')->where('request_id', $requestId)->count() >= 40) throw new RuleViolation('attachment_limit');
                $hash = hash_file('sha256', $file->getRealPath());
                if (DB::table('ppl_attachments')->where('request_id', $requestId)->where('kind', $kind)->where('sha256', $hash)->exists()) throw new RuleViolation('duplicate_attachment', 409);
                $id = (string) Str::uuid();
                $key = 'payment-plan/'.$actor['company_id'].'/'.$requestId.'/'.$id;
                if (!Storage::disk('local')->put($key, file_get_contents($file->getRealPath()))) throw new \RuntimeException('Private attachment write failed.');
                $attachment = ['id' => $id, 'kind' => $kind, 'sha256' => $hash, 'name' => basename($file->getClientOriginalName()), 'type' => $file->getMimeType()];
                DB::table('ppl_attachments')->insert(['id' => $id, 'request_id' => $requestId, 'reconciliation_id' => null, 'company_id' => $actor['company_id'], 'uploader_id' => $actor['id'],
                    'kind' => $kind, 'name' => $attachment['name'], 'mime' => $attachment['type'], 'sha256' => $hash, 'storage_key' => $key, 'created_at' => now()]);
                $payload['attachments'][] = $attachment;
                $response = ['request' => $payload, 'version' => $row->version + 1];
                DB::table('ppl_requests')->where('id', $requestId)->update(['payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'version' => $response['version'], 'updated_at' => now()]);
                DB::table('ppl_events')->insert(['company_id' => $actor['company_id'], 'request_id' => $requestId, 'actor_user_id' => $actor['id'],
                    'mutation_id' => 'attachment:'.$id, 'payload_hash' => $hash, 'event' => 'attachment', 'version' => $response['version'],
                    'result' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                return $response;
            });
        } catch (\Throwable $exception) {
            if ($key !== null) Storage::disk('local')->delete($key);
            throw $exception;
        }
    }
}