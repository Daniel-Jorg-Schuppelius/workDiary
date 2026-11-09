<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetainerMirroredChannelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Billing;

use App\Enums\Billing\{AccountPaymentSource, BillingAgreementMode};
use App\Enums\Finance\{AccountingVoucherState, BillingMode};
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Billing\{CustomerAccountPayment, CustomerBillingAgreement, CustomerBillingRate};
use App\Models\Customer\Customer;
use App\Models\Finance\AccountingVoucher;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{PluginSetting, User};
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillTarget;
use App\Plugins\Easybill\Services\Retainer\{EasybillRetainerPublisher, EasybillRetainerVouchers};
use App\Plugins\SevDesk\Services\Retainer\{SevDeskRetainerPublisher, SevDeskRetainerVouchers};
use App\Plugins\SevDesk\Services\SevDeskTarget;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Billing\{CustomerAccountStatementService, RetainerChannelResolver, RetainerRunner};
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 098, MVP-1505: Pauschal-Modus mit sevDesk und easybill — Pauschale
 * und Spitzabrechnung über die Belegwege des Programms, Zahlstand aus dem
 * Belegspiegel `accounting_vouchers`.
 */
final class RetainerMirroredChannelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const string SEVDESK = 'https://my.sevdesk.de/api/v1';

    private const string EASYBILL = 'https://api.easybill.de/rest/v1';

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        foreach ([SevDeskPlugin::ID, EasybillPlugin::ID] as $pluginId) {
            PluginSetting::create(['organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'tok-' . $pluginId]]);
        }
    }

    public function test_sevdesk_pushes_the_monthly_retainer_once_and_takes_the_sevdesk_number(): void {
        $agreement = $this->retainer(BillingMode::SevDesk, '501');
        $fake = FakePluginHttp::fake($this->sevDeskStubs());

        $invoice = app(RetainerRunner::class)->runFor($agreement, 2026, 3);

        $this->assertNotNull($invoice);
        $this->assertSame(Invoice::TYPE_RETAINER, $invoice->type);
        $this->assertSame('RE-1001', $invoice->number);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $statement = $agreement->statements()->where('year', 2026)->where('month', 3)->firstOrFail();
        $this->assertSame($invoice->id, $statement->retainer_invoice_id);
        $this->assertSame('9001', ExternalReference::query()->where('plugin_id', SevDeskPlugin::ID)->where('external_type', SevDeskTarget::EXT_TYPE_INVOICE)->value('external_id'));
        $save = $this->body($fake, 'POST', '/Invoice/Factory/saveInvoice');
        $this->assertSame(100, $save['invoice']['status']);
        $this->assertEquals(550.0, $save['invoicePosSave'][0]['price']);
        $this->assertSame('Monatspauschale März 2026', $save['invoicePosSave'][0]['name']);
        $fake->assertSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && str_ends_with($r->getUri()->getPath(), '/Invoice/9001/sendBy'));

        $again = app(RetainerRunner::class)->runFor($agreement, 2026, 3);

        $this->assertSame($invoice->id, $again?->id);
        $this->assertCount(1, $this->sent($fake, 'POST', '/Invoice/Factory/saveInvoice'));
    }

    public function test_an_interrupted_sevdesk_push_resumes_without_a_second_invoice(): void {
        $agreement = $this->retainer(BillingMode::SevDesk, '501');
        $fake = FakePluginHttp::fake($this->sevDeskStubs(sendBy: [FakePluginHttp::response(['error' => ['message' => 'Versand nicht möglich']], 422), FakePluginHttp::response(['objects' => ['id' => 9001]])]));

        try {
            app(SevDeskRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);
            $this->fail('Der erste Versand hätte scheitern müssen.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(ValidationException::class, $e);
        }
        $draft = Invoice::query()->where('type', Invoice::TYPE_RETAINER)->sole();
        $this->assertSame(InvoiceStatus::Draft, $draft->status);
        $this->assertSame('SD-R-' . $agreement->id . '-202603', $draft->number);

        $invoice = app(SevDeskRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);

        $this->assertNotNull($invoice);
        $this->assertSame($draft->id, $invoice->id);
        $this->assertSame('RE-1001', $invoice->number);
        $this->assertSame(1, Invoice::query()->where('type', Invoice::TYPE_RETAINER)->count());
        $this->assertCount(1, $this->sent($fake, 'POST', '/Invoice/Factory/saveInvoice'));
    }

    public function test_easybill_creates_and_finishes_the_monthly_retainer(): void {
        $agreement = $this->retainer(BillingMode::Easybill, '601');
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $invoice = app(EasybillRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);

        $this->assertNotNull($invoice);
        $this->assertSame('RE-EB-7', $invoice->number);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $created = $this->body($fake, 'POST', '/documents');
        $this->assertSame('INVOICE', $created['type']);
        $this->assertSame(601, $created['customer_id']);
        $this->assertEquals(55000, $created['items'][0]['single_price_net']);
        $fake->assertSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && str_ends_with($r->getUri()->getPath(), '/documents/7101/done'));
        $this->assertSame('7101', ExternalReference::query()->where('plugin_id', EasybillPlugin::ID)->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)->value('external_id'));
    }

    public function test_the_mirror_books_the_paid_share_net_and_a_cancellation_takes_it_back(): void {
        $agreement = $this->retainer(BillingMode::SevDesk, '501');
        FakePluginHttp::fake($this->sevDeskStubs());
        $invoice = app(SevDeskRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);
        $this->assertNotNull($invoice);
        $mirror = $this->mirrored(SevDeskPlugin::ID, 'invoice:9001', (int) $agreement->customer_id, ['open_amount' => '327.25', 'paid_date' => '2026-04-03']);
        $links = app(SevDeskRetainerVouchers::class);

        $this->assertSame(1, $links->reconcile($this->organization)['booked']);

        $payment = CustomerAccountPayment::query()->sole();
        $this->assertSame(AccountPaymentSource::SevDesk, $payment->source);
        $this->assertSame('275.00', $payment->amount?->getAmount());
        $this->assertSame('2026-04-03', $payment->paid_on->toDateString());
        $this->assertSame($agreement->statements()->where('month', 3)->value('id'), $payment->customer_billing_statement_id);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->refresh()->status);

        $mirror->forceFill(['open_amount' => '0.00', 'voucher_state' => AccountingVoucherState::Paid])->save();
        $links->reconcile($this->organization);
        $this->assertSame('550.00', CustomerAccountPayment::query()->sole()->amount?->getAmount());
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);

        $mirror->forceFill(['voucher_state' => AccountingVoucherState::Cancelled])->save();
        $this->assertSame(1, $links->reconcile($this->organization)['revoked']);
        $this->assertSame(0, CustomerAccountPayment::query()->count());
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->refresh()->status);
    }

    public function test_a_unique_easybill_invoice_is_linked_automatically_and_blocks_a_second_push(): void {
        $agreement = $this->retainer(BillingMode::Easybill, '601');
        $statement = app(CustomerAccountStatementService::class)->ensure($agreement, 2026, 3);
        $this->mirrored(EasybillPlugin::ID, '8801', (int) $agreement->customer_id, ['voucher_date' => '2026-03-31', 'open_amount' => '0.00', 'voucher_state' => AccountingVoucherState::Paid, 'paid_date' => '2026-04-02']);

        $result = app(EasybillRetainerVouchers::class)->reconcile($this->organization);

        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['booked']);
        $this->assertSame('8801', app(EasybillRetainerVouchers::class)->linkedVouchers([$statement])[$statement->id]->externalId);
        $this->assertSame(AccountPaymentSource::Easybill, CustomerAccountPayment::query()->sole()->source);

        try {
            app(EasybillRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);
            $this->fail('Ein verknüpfter Beleg muss den Versand sperren.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('bereits eine Rechnung in easybill verknüpft', $e->validator->errors()->first());
        }
    }

    public function test_ambiguous_invoices_wait_for_a_manual_link_and_unlinking_revokes_the_payment(): void {
        $agreement = $this->retainer(BillingMode::Easybill, '601');
        $statement = app(CustomerAccountStatementService::class)->ensure($agreement, 2026, 3);
        $first = $this->mirrored(EasybillPlugin::ID, '8801', (int) $agreement->customer_id, ['voucher_date' => '2026-03-10', 'open_amount' => '0.00', 'voucher_state' => AccountingVoucherState::Paid]);
        $this->mirrored(EasybillPlugin::ID, '8802', (int) $agreement->customer_id, ['voucher_date' => '2026-03-20']);
        $other = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => BillingMode::Easybill->value]);
        $foreign = $this->mirrored(EasybillPlugin::ID, '8803', (int) $other->id, ['voucher_date' => '2026-03-15']);
        $links = app(EasybillRetainerVouchers::class);

        $this->assertSame(0, $links->reconcile($this->organization)['linked']);
        $this->assertCount(2, $links->linkableVouchers($agreement->customer()->firstOrFail(), $statement));

        try {
            $links->link($statement, (string) $foreign->sqid);
            $this->fail('Ein Beleg eines anderen Kunden darf nicht verknüpft werden.');
        } catch (ValidationException $e) {
            $this->assertSame('Der gewählte Beleg wurde nicht gefunden.', $e->validator->errors()->first());
        }

        $links->link($statement, (string) $first->sqid);
        $links->reconcile($this->organization);
        $this->assertSame('550.00', CustomerAccountPayment::query()->sole()->amount?->getAmount());

        $links->unlink($statement);
        $this->assertSame(0, CustomerAccountPayment::query()->count());
        $this->assertSame([], $links->linkedVouchers([$statement]));
    }

    public function test_the_true_up_bills_the_open_balance_once(): void {
        $agreement = $this->retainer(BillingMode::Easybill, '601');
        $this->entry($agreement, '2026-03-02', 10);
        $fake = FakePluginHttp::fake($this->easybillStubs());

        $invoice = app(EasybillRetainerPublisher::class)->pushTrueUp($agreement);

        $this->assertSame(Invoice::TYPE_RETAINER, $invoice->type);
        $this->assertSame('165.0000', $invoice->items()->firstOrFail()->unit_price?->getAmount());
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertCount(1, $this->sent($fake, 'POST', '/documents'));

        try {
            app(EasybillRetainerPublisher::class)->pushTrueUp($agreement);
            $this->fail('Eine offene Spitzabrechnung muss die nächste sperren.');
        } catch (ValidationException $e) {
            $this->assertSame('Es ist bereits eine unbezahlte Spitzabrechnung offen.', $e->validator->errors()->first());
        }
    }

    public function test_without_the_plugin_the_push_is_refused_with_the_program_name(): void {
        $agreement = $this->retainer(BillingMode::SevDesk, '501');
        PluginSetting::query()->where('plugin_id', SevDeskPlugin::ID)->update(['enabled' => false]);

        try {
            app(SevDeskRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);
            $this->fail('Ohne eingeschaltetes Plugin darf nichts gesendet werden.');
        } catch (ValidationException $e) {
            $this->assertSame('sevDesk ist nicht aktiviert oder nicht vollständig eingerichtet.', $e->validator->errors()->first());
        }
    }

    public function test_the_retainer_agreement_saves_for_sevdesk_and_easybill(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->assertTrue(app(RetainerChannelResolver::class)->supports(BillingMode::SevDesk));
        $this->assertTrue(app(RetainerChannelResolver::class)->supports(BillingMode::Easybill));

        foreach ([BillingMode::SevDesk, BillingMode::Easybill] as $mode) {
            $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'billing_mode' => $mode->value]);
            $this->actingAs($admin)
                ->post(route('customers.billing.agreement.save', $customer), [
                    'mode' => 'retainer', 'currency' => 'EUR', 'workdays_per_week' => 6, 'expected_monthly_amount' => '550', 'active' => '1',
                    'rate_activity_category_id' => [''], 'rate_day_type' => ['weekday'], 'rate_hourly_rate' => ['16.50'],
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('customers.show', $customer));
            $this->assertSame(BillingAgreementMode::Retainer, $customer->billingAgreement()->firstOrFail()->mode);
        }
    }

    public function test_the_mirror_run_reconciles_easybill_retainers(): void {
        $agreement = $this->retainer(BillingMode::Easybill, '601');
        FakePluginHttp::fake($this->easybillStubs());
        $invoice = app(EasybillRetainerPublisher::class)->pushMonthlyRetainer($agreement, 2026, 3);
        $this->assertNotNull($invoice);
        FakePluginHttp::fake([
            self::EASYBILL . '/positions*' => FakePluginHttp::response(['page' => 1, 'pages' => 1, 'limit' => 100, 'total' => 0, 'items' => []]),
            self::EASYBILL . '/documents*' => static function (RequestInterface $request): Psr7Response {
                $items = str_contains(urldecode($request->getUri()->getQuery()), 'type=RECURRING') ? [] : [[
                    'id' => 7101, 'type' => 'INVOICE', 'number' => 'RE-EB-7', 'customer_id' => 601, 'currency' => 'EUR',
                    'document_date' => '2026-04-01', 'amount' => 65450, 'amount_net' => 55000, 'paid_amount' => 65450,
                    'paid_at' => '2026-04-05', 'is_draft' => false, 'edited_at' => '2026-04-05 10:00:00', 'items' => [],
                ]];

                return FakePluginHttp::response(['page' => 1, 'pages' => 1, 'limit' => 100, 'total' => count($items), 'items' => $items]);
            },
        ]);

        Artisan::call('accounting:pull-vouchers', ['--plugin' => EasybillPlugin::ID, '--organization' => $this->organization->id]);

        $payment = CustomerAccountPayment::query()->sole();
        $this->assertSame(AccountPaymentSource::Easybill, $payment->source);
        $this->assertSame('550.00', $payment->amount?->getAmount());
        $this->assertSame(InvoiceStatus::Paid, $invoice->refresh()->status);
    }

    private function retainer(BillingMode $mode, string $contactId): CustomerBillingAgreement {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Praxis Sonnenhof', 'billing_mode' => $mode->value]);
        ExternalReference::create([
            'organization_id' => $this->organization->id, 'plugin_id' => $mode->value, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $customer->getMorphClass(), 'referenceable_id' => $customer->id, 'external_id' => $contactId, 'synced_at' => now(),
        ]);
        $agreement = CustomerBillingAgreement::factory()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id,
            'mode' => 'retainer', 'expected_monthly_amount' => 550.00, 'workdays_per_week' => 6,
        ]);
        CustomerBillingRate::factory()->create([
            'organization_id' => $this->organization->id, 'customer_billing_agreement_id' => $agreement->id,
            'day_type' => 'weekday', 'hourly_rate' => 16.50,
        ]);

        return $agreement;
    }

    private function entry(CustomerBillingAgreement $agreement, string $day, int $hours): void {
        $project = Project::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $agreement->customer_id]);
        TimeEntry::create([
            'organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'project_id' => $project->id,
            'kind' => TimeEntryKind::Work->value, 'billable' => true,
            'started_at' => $day . ' 08:00:00', 'ended_at' => $day . ' ' . (8 + $hours) . ':00:00',
        ]);
        app(CustomerAccountStatementService::class)->recalculateOpen($agreement);
    }

    /** @param  array<string, mixed>  $attributes */
    private function mirrored(string $pluginId, string $externalId, int $customerId, array $attributes = []): AccountingVoucher {
        return AccountingVoucher::factory()->create($attributes + [
            'organization_id' => $this->organization->id, 'plugin_id' => $pluginId, 'external_id' => $externalId,
            'customer_id' => $customerId, 'direction' => 'outgoing', 'document_kind' => 'invoice',
            'voucher_state' => AccountingVoucherState::Open, 'voucher_number' => 'RE-' . $externalId,
            'voucher_date' => '2026-04-01', 'total_amount' => '654.50', 'net_amount' => '550.00', 'open_amount' => '654.50',
        ]);
    }

    /**
     * @param  list<Psr7Response>|null  $sendBy
     * @return array<string, mixed>
     */
    private function sevDeskStubs(?array $sendBy = null): array {
        return [
            self::SEVDESK . '/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => '1.0']]),
            self::SEVDESK . '/SevUser*' => FakePluginHttp::response(['objects' => [['id' => 7]]]),
            self::SEVDESK . '/SevSequence/Factory/getByType*' => FakePluginHttp::response(['objects' => ['nextSequence' => '1001', 'format' => 'RE-[%nr]']]),
            self::SEVDESK . '/Invoice/Factory/saveInvoice' => FakePluginHttp::response(['objects' => ['invoice' => ['id' => 9001, 'status' => '100']]], 201),
            self::SEVDESK . '/Invoice/9001/sendBy' => $sendBy ?? FakePluginHttp::response(['objects' => ['id' => 9001, 'status' => '200']]),
            self::SEVDESK . '/Invoice/9001' => FakePluginHttp::response(['objects' => [['id' => 9001, 'invoiceNumber' => 'RE-1001', 'status' => '100']]]),
            self::SEVDESK . '/Contact/501' => FakePluginHttp::response(['objects' => ['id' => 501]]),
        ];
    }

    /** @return array<string, mixed> */
    private function easybillStubs(): array {
        return [
            self::EASYBILL . '/customers/601' => FakePluginHttp::response(['id' => 601]),
            self::EASYBILL . '/documents/7101/done' => FakePluginHttp::response(['id' => 7101, 'number' => 'RE-EB-7']),
            self::EASYBILL . '/documents' => FakePluginHttp::response(['id' => 7101], 201),
        ];
    }

    /** @return list<RequestInterface> */
    private function sent(FakePluginHttp $fake, string $method, string $pathSuffix): array {
        return array_values(array_map(
            static fn (array $call): RequestInterface => $call['request'],
            array_filter($fake->recorded(), static fn (array $call): bool => $call['request']->getMethod() === $method && str_ends_with($call['request']->getUri()->getPath(), $pathSuffix)),
        ));
    }

    /** @return array<string, mixed> */
    private function body(FakePluginHttp $fake, string $method, string $pathSuffix): array {
        $requests = $this->sent($fake, $method, $pathSuffix);
        $this->assertNotEmpty($requests);

        return (array) json_decode((string) $requests[array_key_last($requests)]->getBody(), true);
    }
}
