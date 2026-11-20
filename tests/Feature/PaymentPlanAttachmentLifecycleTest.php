<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\BankRequestStore;
use App\Modules\PaymentPlan\Services\PrivateAttachmentStore;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentPlanAttachmentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ?string $privateStorageRoot = null;

    protected function tearDown(): void
    {
        try {
            if ($this->privateStorageRoot !== null) {
                (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->privateStorageRoot);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function test_failed_upload_audit_rolls_back_evidence_and_blob_then_allows_clean_retry(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        if (getenv('R1_STORAGE_REAL_LOCAL') === '1') {
            $this->assertTrue(app()->environment('testing'));
            $this->privateStorageRoot = sys_get_temp_dir().'/r1-attachment-'.Str::uuid();
            $this->assertTrue(mkdir($this->privateStorageRoot, 0700));
            config(['filesystems.disks.local' => ['driver' => 'local', 'root' => $this->privateStorageRoot, 'throw' => true]]);
            Storage::forgetDisk('local');
        } else {
            Storage::fake('local');
        }
        config(['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.erp_bank_balance' => false, 'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false]);
        $user = User::factory()->create();
        $this->actingAs($user);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $outsider = User::factory()->create();
        $role = \App\Modules\Platform\Models\Role::create(['code' => 'PPL_REQUESTER', 'name' => 'Requester']);
        foreach ([$user, $outsider] as $member) {
            $member->companies()->attach($company->id, ['is_default' => true]);
            $member->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => '{}']);
        $actor = ['id' => (string) $user->id, 'name' => $user->name, 'department' => 'Test',
            'company_id' => $company->id, 'roles' => ['requester']];
        $id = (string) Str::uuid();
        (new BankRequestStore())->create(['id' => $id, 'payee' => 'Synthetic supplier',
            'subject' => ['Uji', 'Test', 'Test'], 'purpose' => ['Uji', 'Test', 'Test'],
            'amount' => '100.25', 'recipientBank' => 'Fixture', 'recipientAccount' => 'TEST',
            'due' => now()->addDay()->toDateString()], $actor);
        $before = (array) DB::table('ppl_requests')->where('id', $id)->first();
        $file = UploadedFile::fake()->createWithContent('evidence.pdf', "%PDF-1.4\nSynthetic evidence\n%%EOF");
        DB::unprepared("CREATE TEMP TRIGGER reject_attachment_audit BEFORE INSERT ON ppl_attachment_audits BEGIN SELECT RAISE(ABORT, 'injected attachment audit failure'); END");
        try {
            (new PrivateAttachmentStore())->add($id, 1, 'request', $file, $actor);
            $this->fail('The injected audit failure must abort the upload.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('injected attachment audit failure', $exception->getMessage());
        } finally {
            DB::unprepared('DROP TRIGGER reject_attachment_audit');
        }
        $this->assertSame($before, (array) DB::table('ppl_requests')->where('id', $id)->first());
        $this->assertDatabaseCount('ppl_attachments', 0);
        $this->assertDatabaseCount('ppl_attachment_audits', 0);
        $this->assertDatabaseCount('ppl_events', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('payment-plan'));

        $upload = $this->post(route('payment-plan-v33.attachment'), [
            'id' => $id, 'version' => 1, 'kind' => 'request', 'file' => $file,
        ]);
        $this->assertSame(200, $upload->getStatusCode(), $upload->getContent());
        $upload->assertJsonPath('version', 2);
        $attachment = DB::table('ppl_attachments')->sole();
        $this->assertSame($attachment->sha256, hash('sha256', Storage::disk('local')->get($attachment->storage_key)));
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'actor_id' => $user->id, 'action' => 'upload']);
        try {
            (new PrivateAttachmentStore())->add($id, 2, 'request', $file, $actor);
            $this->fail('A duplicate must not append evidence.');
        } catch (RuleViolation $exception) {
            $this->assertSame('duplicate_attachment', $exception->rule);
        }
        $this->assertDatabaseCount('ppl_attachments', 1);
        $this->assertDatabaseCount('ppl_attachment_audits', 1);
        $this->assertDatabaseCount('ppl_events', 2);
        $this->assertCount(1, Storage::disk('local')->allFiles('payment-plan'));

        $original = Storage::disk('local')->get($attachment->storage_key);
        foreach (['missing' => 404, 'tampered' => 409] as $scenario => $status) {
            if ($scenario === 'missing') {
                Storage::disk('local')->delete($attachment->storage_key);
            } else {
                Storage::disk('local')->put($attachment->storage_key, 'tampered evidence');
            }
            try {
                (new PrivateAttachmentStore())->response($attachment);
                $this->fail('Unavailable or corrupted evidence must not be downloaded.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame($status, $exception->getStatusCode());
            }
            $this->assertDatabaseCount('ppl_attachment_audits', 1);
            $this->assertSame($attachment->sha256, DB::table('ppl_attachments')->where('id', $attachment->id)->value('sha256'));
        }
        Storage::disk('local')->put($attachment->storage_key, $original);
        $url = route('payment-plan-v33.download', $attachment->id);
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->get($url.'?preview=1')->assertForbidden();
        $this->assertDatabaseCount('ppl_attachment_audits', 1);
        $response = $this->actingAs($user)->get($url)->assertOk();
        $this->assertSame($attachment->sha256, hash('sha256', $response->streamedContent()));
        $this->assertDatabaseHas('ppl_attachment_audits', ['attachment_id' => $attachment->id, 'actor_id' => $user->id, 'action' => 'download']);
        $this->assertDatabaseCount('ppl_attachment_audits', 2);
    }
}