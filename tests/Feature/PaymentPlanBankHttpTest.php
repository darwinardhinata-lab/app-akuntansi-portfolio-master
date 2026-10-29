<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentPlanBankHttpTest extends TestCase
{
    use RefreshDatabase;

    public static function paymentCases(): array { return ['direct service' => [false], 'partial AP invoice' => [true]]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('paymentCases')]
    public function test_actual_bank_http_flow_with_private_evidence_reaches_single_erp_journal(bool $ap): void
    {
        Storage::fake('local');
        config(['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.reservations' => true, 'payment_plan_v33.posting' => true, 'platform.posting_intents_enabled' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'MGI', 'base_currency' => 'IDR', 'active' => true]);
        $users = [];
        foreach (['req' => 'REQUESTER', 'dir' => 'DIRECTOR', 'fs' => 'FINANCE_STAFF', 'mk' => 'MAKER', 'gm' => 'GENERAL_MANAGER', 'acc' => 'ACCOUNTANT'] as $key => $code) {
            $users[$key] = User::factory()->create();
            $users[$key]->companies()->attach($company->id, ['is_default' => true]);
            $role = Role::create(['code' => 'PPL_'.$code, 'name' => $code]);
            $users[$key]->platformRoles()->attach($role->id, ['company_id' => $company->id]);
        }
        $settings = ['version' => 1, 'accounts' => [['id' => 'idr3', 'name' => 'Synthetic bank', 'number' => 'TEST-ONLY', 'currency' => 'IDR', 'payment' => true, 'opening' => '500.00', 'asOf' => '2026-01-01']], 'receipts' => [], 'cash' => ['opening' => null, 'asOf' => null], 'cashReceipts' => []];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings)]);
        foreach (['expense' => '610001', 'bank.idr3' => '111003'] as $key => $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Fixture '.$key, 'coa_type' => 'Other', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
            DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.'.$key, 'account_code' => $code, 'active' => true]);
        }
        $billId = null;
        $baselineJournals = 0;
        if ($ap) {
            config(['payment_plan_v33.ap_allocations' => true, 'payment_plan_v33.erp_bank_balance' => true]);
            DB::table('accounts')->insert(['account_code' => '211001', 'account_name' => 'Fixture payable', 'coa_type' => 'Other', 'normal_balance' => 'KREDIT', 'report_pos' => 'NERACA']);
            \App\Support\PostingService::post(['transaction_date' => '2025-12-31'], [
                ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '500.00'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '500.00'],
            ]);
            $source = \App\Support\PostingService::post(['transaction_date' => '2026-01-01'], [
                ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.50'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '200.50'],
            ]);
            $billId = DB::table('purchase_bills')->insertGetId(['bill_number' => 'HTTP-AP', 'bill_date' => '2026-01-01', 'vendor_name' => 'AP supplier', 'credit_account' => '211001', 'grand_total' => '200.50', 'journal_id' => $source->getKey(), 'payment_status' => 'UNPAID']);
            $settings['erp_cutoff'] = '2026-01-01';
            DB::table('ppl_financial_contexts')->where('company_id', $company->id)->update(['settings' => json_encode($settings)]);
            $baselineJournals = 2;
        }
        config(['platform.filament_enabled' => true]);
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        Role::where('code', 'PPL_REQUESTER')->firstOrFail()->permissions()->syncWithoutDetaching(
            \App\Modules\Platform\Models\Permission::where('code', 'erp.panel.access')->pluck('id'));
        $this->actingAs($users['req'])->get(route('payment-plan-v33.workspace'))->assertRedirect('/erp-panel/bank-payments');
        $screen = $this->get('/erp-panel/bank-payments')->assertOk()->assertSee('newBankRequest')->assertSee('bankInbox');
        if (getenv('R1_UI_CAPTURE') && !$ap) file_put_contents(getenv('R1_UI_CAPTURE').'/screen.html', $screen->getContent());
        $id = '404533da-f658-4f80-afc9-7e438ed4a529';
        $requestInput = ['id' => $id, 'payee' => 'Synthetic supplier',
            'subject' => ['Jasa', 'Service', '服务'], 'purpose' => ['Uji', 'Test', '测试'], 'amount' => '100.25',
            'recipientBank' => 'Fixture', 'recipientAccount' => 'TEST', 'due' => now()->addDay()->toDateString()];
        if ($ap) $requestInput['purchaseBillId'] = $billId;
        $requestInput['lines'] = [
            ['description' => ['Item satu', 'First service', '服务一'], 'qty' => '2', 'unitPrice' => '25.00'],
            ['description' => ['Item dua', 'Second service', '服务二'], 'qty' => '1', 'unitPrice' => '50.25'],
        ];
        $bad = $requestInput; $bad['amount'] = '100.24';
        $this->postJson(route('payment-plan-v33.request'), $bad)->assertStatus(422)->assertJsonPath('error', 'line_total_mismatch');
        $created = $this->postJson(route('payment-plan-v33.request'), $requestInput)->assertCreated()->json();
        $version = $created['version'];
        $this->get(route('payment-plan-v33.print', $id))->assertOk()
            ->assertSee($ap ? 'AP supplier' : 'Synthetic supplier')->assertSee('First service')->assertSee('Second service')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('payment-plan-v33.print', 'missing'))->assertNotFound();
        $outsider = User::factory()->create();
        $outsider->companies()->attach($company->id, ['is_default' => true]);
        $requesterRole = Role::where('code', 'PPL_REQUESTER')->firstOrFail();
        $outsider->platformRoles()->attach($requesterRole->id, ['company_id' => $company->id]);
        $this->actingAs($outsider)->get(route('payment-plan-v33.print', $id))->assertForbidden();
        $this->actingAs($users['req']);
        $this->getJson(route('payment-plan-v33.purchase-bills'))->assertStatus($ap ? 403 : 404);
        if ($ap) {
            $this->actingAs($users['fs'])->getJson(route('payment-plan-v33.purchase-bills', ['q' => 'HTTP-AP']))
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('items.0.residual', '200.50');
            $this->getJson(route('payment-plan-v33.purchase-bills', ['q' => 'not-present']))->assertOk()->assertJsonPath('total', 0);
            $this->getJson(route('payment-plan-v33.purchase-bills', ['page' => 0]))->assertUnprocessable();
            foreach (range(1, 21) as $index) {
                DB::table('purchase_bills')->insert(['bill_number' => 'LOOKUP-'.$index, 'bill_date' => '2026-01-01', 'vendor_name' => 'Pagination supplier',
                    'credit_account' => '211001', 'grand_total' => '200.50', 'journal_id' => $source->getKey(), 'payment_status' => 'UNPAID']);
            }
            $this->getJson(route('payment-plan-v33.purchase-bills', ['q' => 'Pagination supplier', 'page' => 2]))
                ->assertOk()->assertJsonPath('total', 21)->assertJsonPath('lastPage', 2)->assertJsonCount(1, 'items');
        }
        $action = function ($who, $name, $input) use (&$version, $users, $id) {
            $response = $this->actingAs($users[$who])->postJson(route('payment-plan-v33.action'), [
                'id' => $id, 'version' => $version, 'mutationId' => 'http-bank-'.$name, 'action' => $name, 'input' => $input,
            ])->assertOk()->json();
            $version = $response['version'];
            return $response;
        };
        $photo = $this->actingAs($users['req'])->post(route('payment-plan-v33.attachment'), [
            'id' => $id, 'version' => $version, 'kind' => 'request',
            'file' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS9kAAAAASUVORK5CYII=')),
        ])->assertOk()->json();
        $version = $photo['version'];
        if (getenv('R1_UI_CAPTURE') && !$ap) file_put_contents(getenv('R1_UI_CAPTURE').'/photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jS9kAAAAASUVORK5CYII='));
        $action('req', 'submit', []);
        $action('dir', 'approve_request', ['checked' => true]);
        $upload = function ($kind) use (&$version, $id, $users) {
            $response = $this->actingAs($users['fs'])->post(route('payment-plan-v33.attachment'), [
                'id' => $id, 'version' => $version, 'kind' => $kind,
                'file' => UploadedFile::fake()->createWithContent($kind.'.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF"),
            ])->assertOk()->json();
            $version = $response['version'];
            return $response;
        };
        $upload('settlement');
        $action('fs', 'prepare_settlement', ['checked' => true, 'base' => '100.25', 'coa' => '610001', 'sourceId' => 'idr3']);
        $action('dir', 'approve_settlement', ['checked' => true]);
        DB::table('company_coa_mappings')->where('semantic_key', 'ppl.bank.idr3')->update(['active' => false]);
        $this->actingAs($users['mk'])->postJson(route('payment-plan-v33.action'), ['id' => $id, 'version' => $version,
            'mutationId' => 'changed-mapping-maker', 'action' => 'mark_maker', 'input' => ['reference' => 'INSTRUCTION']])->assertStatus(409);
        $this->assertDatabaseCount('ppl_reservations', 0);
        DB::table('company_coa_mappings')->where('semantic_key', 'ppl.bank.idr3')->update(['active' => true]);
        $action('mk', 'mark_maker', ['reference' => 'INSTRUCTION']);
        $this->assertDatabaseHas('ppl_reservations', ['request_id' => $id, 'status' => 'active', 'amount' => '100.25']);
        $action('gm', 'release_gm', ['checked' => true, 'reference' => 'RELEASE']);
        $uploaded = $upload('payment');
        $attachment = end($uploaded['request']['attachments']);
        $this->get(route('payment-plan-v33.download', $attachment['id']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $action('fs', 'confirm_paid', ['checked' => true, 'reference' => 'ACTUAL-DEBIT', 'paidDate' => now('Asia/Jakarta')->toDateString()]);
        $this->assertDatabaseCount('journal_headers', $baselineJournals);
        DB::table('company_coa_mappings')->where('semantic_key', 'ppl.bank.idr3')->update(['active' => false]);
        $this->assertDatabaseHas('ppl_requests', ['id' => $id, 'status' => 'paid', 'version' => $version]);
        $this->assertDatabaseCount('journal_headers', $baselineJournals);
        $result = $action('acc', 'post_external', ['checked' => true, 'reference' => 'ERP']);
        DB::table('company_coa_mappings')->where('semantic_key', 'ppl.bank.idr3')->update(['active' => true]);
        $this->assertSame('posted', $result['request']['status']);
        if (getenv('R1_UI_CAPTURE') && !$ap) {
            file_put_contents(getenv('R1_UI_CAPTURE').'/print.html', $this->get(route('payment-plan-v33.print', $id))->assertOk()->getContent());
            file_put_contents(getenv('R1_UI_CAPTURE').'/data.json', json_encode(['requests'=>[$result], 'bankBalances'=>[], 'apReconciliation'=>[]]));
        }
        $this->get(route('payment-plan-v33.print', $id))->assertOk()
            ->assertSee('111003')->assertSee('100.25')->assertSee('receipt.png')->assertSee('<img', false)->assertSee($result['request']['journal']['journalId']);
        $this->assertDatabaseHas('ppl_payment_events', ['request_id' => $id, 'journal_id' => $result['request']['journal']['journalId']]);
        $this->assertDatabaseCount('journal_headers', $baselineJournals + 1);
        $this->assertSame(2, DB::table('journal_details')->where('journal_id', $result['request']['journal']['journalId'])->count());
        if ($ap) {
            $this->assertDatabaseHas('journal_details', ['journal_id' => $result['request']['journal']['journalId'], 'account_code' => '211001', 'position' => 'DEBET', 'amount' => '100.25']);
            $this->assertDatabaseMissing('journal_details', ['journal_id' => $result['request']['journal']['journalId'], 'account_code' => '610001']);
            $this->assertSame('100.25', DB::transaction(fn () => (new \App\Modules\PaymentPlan\Services\PurchaseBillAllocation())->source($billId, $company->id))['residual']);
            $data = $this->getJson(route('payment-plan-v33.data'))->assertOk();
            $data->assertJsonPath('bankBalances.idr3.actual', '399.75')->assertJsonPath('bankBalances.idr3.unposted_actual_debits', '0.00');
            $data->assertJsonPath('purchaseBills.0.residual', '100.25');
        }
        $this->assertDatabaseHas('journal_details', ['position' => 'DEBET', 'amount' => '100.25']);
        $this->assertDatabaseHas('journal_details', ['position' => 'KREDIT', 'amount' => '100.25']);
        $this->get(route('payment-plan-v33.print', $id))->assertOk()
            ->assertSee($result['request']['journal']['journalId'])->assertSee('111003')->assertSee('payment.pdf')->assertDontSee('bank_payment.sourceId');
        config(['payment_plan_v33.bank_workflow' => false]);
        $this->get(route('payment-plan-v33.print', $id))->assertNotFound();
    }
}