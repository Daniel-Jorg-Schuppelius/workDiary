<?php
/*
 * Created on   : Sat Oct 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EbicsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\EbicsConnectionStatus;
use App\Models\Audit\AuditLog;
use App\Models\Document\Document;
use App\Models\Finance\{BankAccount, BankStatement, EbicsConnection};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\{Organization, User};
use App\Services\Billing\FinancialFormatsSupport;
use App\Services\Billing\Sepa\PaymentRunService;
use App\Services\Finance\Ebics\Contracts\EbicsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEbicsGateway;
use Tests\TestCase;

/** MVP-124: EBICS-Einrichtung Schritt für Schritt, Auszugsabruf in den Bankimport, Zahllauf-Einreichung. */
final class EbicsTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private BankAccount $account;

    private FakeEbicsGateway $gateway;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->account = BankAccount::factory()->create([
            'organization_id' => $this->org->id, 'label' => 'Geschäftskonto',
            'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'account_holder' => 'Muster GmbH',
        ]);
        $this->app->instance(EbicsGateway::class, $this->gateway = new FakeEbicsGateway);
    }

    private function setupAccess(): void {
        $this->actingAs($this->admin)->put(route('finance.bank-accounts.ebics.update', $this->account->sqid), [
            'host_url' => 'https://ebics.bank.example/ebicsweb', 'ebics_host' => 'BANKHOST', 'ebics_partner' => 'K1234', 'ebics_user' => 'U001',
        ])->assertSessionHas('success');
    }

    private function activeConnection(): EbicsConnection {
        $this->setupAccess();
        foreach (['keys', 'initialize', 'activate'] as $step) {
            $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.' . $step, $this->account->sqid))->assertSessionHas('success');
        }

        return EbicsConnection::query()->firstOrFail();
    }

    public function test_setup_runs_step_by_step_with_letter_and_keeps_secrets_out_of_the_audit(): void {
        $this->actingAs($this->admin)->put(route('finance.bank-accounts.ebics.update', $this->account->sqid), [
            'host_url' => 'http://intern.local/ebics', 'ebics_host' => 'BANK HOST', 'ebics_partner' => 'K1', 'ebics_user' => 'U1',
        ])->assertSessionHasErrors(['host_url', 'ebics_host']);

        $this->setupAccess();
        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.activate', $this->account->sqid))
            ->assertSessionHas('error', __('ebics.error.invalid_step'));
        $this->actingAs($this->admin)->get(route('finance.bank-accounts.ebics.letter', $this->account->sqid))
            ->assertSessionHas('error', __('ebics.error.not_initialized'));

        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.keys', $this->account->sqid))->assertSessionHas('success');
        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.initialize', $this->account->sqid))->assertSessionHas('success');
        $letter = $this->actingAs($this->admin)->get(route('finance.bank-accounts.ebics.letter', $this->account->sqid))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $letter->getContent());
        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.activate', $this->account->sqid))->assertSessionHas('success');

        $connection = EbicsConnection::query()->firstOrFail();
        $this->assertSame(EbicsConnectionStatus::Active, $connection->status);
        $this->assertSame(['keys', 'initialize', 'activate'], $this->gateway->calls);
        $this->assertSame(['ebics_setup_saved', 'ebics_keys_created', 'ebics_initialized', 'ebics_letter_printed', 'ebics_activated'], $connection->journal->pluck('event')->all());

        // Zugangsdaten sind nach den Schlüsseln fest; Geheimnisse nie im Audit.
        $this->actingAs($this->admin)->put(route('finance.bank-accounts.ebics.update', $this->account->sqid), [
            'host_url' => 'https://ebics.bank.example/ebicsweb', 'ebics_host' => 'OTHERHOST', 'ebics_partner' => 'K1234', 'ebics_user' => 'U001',
        ])->assertSessionHas('error', __('ebics.error.locked_after_keys'));
        $audit = AuditLog::query()->get()->map(static fn (AuditLog $log): string => (string) json_encode($log->toArray()))->implode("\n");
        $this->assertStringNotContainsString('secret-passphrase', $audit);
        $this->assertStringNotContainsString('{"fake":true}', $audit);
        $this->actingAs($this->admin)->get(route('finance.bank-accounts.ebics.show', $this->account->sqid))->assertOk()->assertDontSee('secret-passphrase');
    }

    public function test_bank_errors_are_journaled_and_reported(): void {
        $this->setupAccess();
        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.keys', $this->account->sqid));
        $this->gateway->failWith = '091002';

        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.initialize', $this->account->sqid))
            ->assertSessionHas('error', __('ebics.error.bank_rejected') . ' (091002)');
        $connection = EbicsConnection::query()->firstOrFail();
        $this->assertSame(EbicsConnectionStatus::KeysCreated, $connection->status);
        $this->assertSame('ebics_failed', $connection->journal->last()?->event);
        $this->assertNotNull($connection->last_error);
    }

    public function test_statements_are_fetched_into_the_bank_import_once(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $connection = $this->activeConnection();
        $this->gateway->statements = [(string) file_get_contents(base_path('tests/Fixtures/finance/camt053_sample.xml'))];

        $this->actingAs($this->admin)->post(route('finance.bank-accounts.ebics.fetch', $this->account->sqid))->assertSessionHas('success');
        $this->assertSame(1, BankStatement::query()->count());
        $this->assertSame((int) $this->account->id, (int) BankStatement::query()->firstOrFail()->bank_account_id);
        $this->assertStringContainsString('download:' . CarbonImmutable::today()->subDays(30)->toDateString(), implode(' ', $this->gateway->calls));

        // Derselbe Auszug noch einmal: der Import erkennt ihn, der nächste Abruf beginnt beim Folgetag.
        $this->artisan('finance:ebics-statements')->assertSuccessful();
        $this->assertSame(1, BankStatement::query()->count());
        $this->assertStringContainsString('download:' . CarbonImmutable::today()->toDateString(), (string) end($this->gateway->calls));
        $this->assertSame('ebics_statements_fetched', $connection->refresh()->journal->last()?->event);
    }

    public function test_released_payment_run_is_submitted_once(): void {
        if (! FinancialFormatsSupport::isAvailable()) {
            $this->markTestSkipped('php-financial-formats nicht verfügbar.');
        }
        $this->activeConnection();
        $invoice = IncomingEInvoice::query()->create([
            'organization_id' => $this->org->id, 'document_id' => Document::factory()->create(['organization_id' => $this->org->id])->id,
            'sha256' => hash('sha256', 'ebics-run'), 'source' => 'upload', 'received_at' => now(),
            'status' => IncomingEInvoice::STATUS_PAYMENT_RELEASED, 'invoice_number' => 'RE-4711', 'seller_name' => 'Lieferant GmbH',
            'issue_date' => CarbonImmutable::today()->subDays(3)->toDateString(), 'due_date' => CarbonImmutable::today()->addDays(27)->toDateString(),
            'currency' => 'EUR', 'amount_gross' => '1190.00', 'creditor_iban' => 'DE89370400440532013000', 'creditor_bic' => 'COBADEFFXXX',
        ]);
        $runs = app(PaymentRunService::class);
        $run = $runs->release($runs->createFromProposals($this->account, $this->admin, [$invoice->id]), $this->admin);

        $this->actingAs($this->admin)->post(route('finance.payment-runs.ebics', $run))->assertSessionHas('success', __('ebics.flash.submitted', ['order' => 'A001']));
        $this->assertCount(1, $this->gateway->uploads);
        $this->assertStringContainsString('pain.001', $this->gateway->uploads[0]['xml']);
        $this->assertSame('credit_transfer', $this->gateway->uploads[0]['kind']->value);
        $this->assertTrue($run->refresh()->isExported(), 'Eingereicht wird die archivierte Datei.');

        $this->actingAs($this->admin)->post(route('finance.payment-runs.ebics', $run))->assertSessionHas('error', __('ebics.error.already_submitted'));
        $this->assertCount(1, $this->gateway->uploads);
    }
}
