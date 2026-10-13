<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxDocumentIssuerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Enums\Billing\DocumentRelationKind;
use App\Enums\Invoicing\InvoiceStatus;
use App\Enums\Numbering\NumberScope;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Plugins\OrgaMax\Enums\OrgaMaxConnectionStatus;
use App\Plugins\OrgaMax\Models\OrgaMaxConnection;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Plugins\OrgaMax\Services\OrgaMaxDocumentIssuer;
use App\Services\Billing\DocumentRelations;
use App\Services\Invoicing\QuoteService;
use App\Services\Numbering\NumberSequenceService;
use App\Services\Sales\OrderConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145 (MVP-1161/1164/1166): orgaMAX nummeriert Angebot, AB und Rechnung. */
final class OrgaMaxDocumentIssuerTest extends TestCase {
    use OrgaMaxApiResponses;
    use RefreshDatabase;

    private const API = 'https://api.orgamax.de/openapi/';

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
        $this->user = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH', 'number' => 'K-1001']);
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => OrgaMaxPlugin::ID, 'enabled' => true, 'settings' => []]);
        OrgaMaxConnection::create([
            'organization_id' => $this->org->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnectionStatus::Active,
        ]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => OrgaMaxPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => 'om-7']);
    }

    /** @param array<string, \Closure> $routes */
    private function fake(array $routes): FakePluginHttp {
        // GET (Namensabgleich, B8) und PUT des Kontakt-Push teilen die URL — je Aufruf eine frische Antwort.
        return FakePluginHttp::fake([self::API . 'customer/om-7' => fn () => self::itemResponse(['id' => 'om-7'])] + $routes);
    }

    /** @return array{quote: \App\Models\Sales\Quote, acceptance_token: string} */
    private function sentQuote(): array {
        $service = app(QuoteService::class);
        $quote = $service->create(['customer_id' => $this->customer->id, 'valid_until' => now()->addWeeks(2)->toDateString()], [
            ['description' => 'Pumpe P-200', 'quantity' => 2, 'unit' => 'Stk', 'unit_price' => 500, 'tax_rate' => '19.00'],
            ['description' => 'Wartung (optional)', 'quantity' => 1, 'unit_price' => 120, 'tax_rate' => '19.00', 'optional' => true],
        ], $this->user);

        return $service->send($service->approve($quote, $this->user), $this->user);
    }

    public function test_quote_becomes_an_orgamax_offer_with_its_number(): void {
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::Quote, ['source' => OrgaMaxPlugin::ID]);
        $fake = $this->fake([
            self::API . 'offer/501' => fn () => self::itemResponse(['id' => 501, 'number' => 'AN-1001']),
            self::API . 'offer/' => fn () => self::itemResponse(['id' => 501, 'number' => 'AN-1001'], 201),
        ]);

        $quote = $this->sentQuote()['quote'];

        $this->assertSame('AN-1001', $quote->number);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && str_ends_with((string) $request->getUri(), '/offer/')
                && count((array) ($body['positions'] ?? [])) === 1
                && (float) ($body['positions'][0]['vatPercent'] ?? 0) === 19.0;
        });
        $relations = collect(app(DocumentRelations::class)->of($quote))->keyBy(fn (array $r): string => (string) $r['node']->number);
        $this->assertSame(DocumentRelationKind::Counterpart, $relations['AN-1001']['kind'] ?? null);
    }

    public function test_order_confirmation_becomes_an_orgamax_order(): void {
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::OrderConfirmation, ['source' => OrgaMaxPlugin::ID]);
        ['quote' => $quote, 'acceptance_token' => $token] = $this->sentQuote();
        $quote = app(QuoteService::class)->accept($quote, null, $token);
        $confirmations = app(OrderConfirmationService::class);
        $confirmation = $confirmations->fromQuote($quote, $this->user);
        $this->fake([
            self::API . 'order/601' => fn () => self::itemResponse(['id' => 601, 'number' => 'AU-1001']),
            self::API . 'order/' => fn () => self::itemResponse(['id' => '601'], 201),
        ]);

        $confirmation = $confirmations->confirm($confirmation, $this->user);

        $this->assertSame('AU-1001', $confirmation->number);
    }

    public function test_invoice_goes_through_order_conversion_and_lock(): void {
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::Invoice, ['source' => OrgaMaxPlugin::ID]);
        $invoice = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => app(NumberSequenceService::class)->next($this->org, NumberScope::Invoice),
            'status' => InvoiceStatus::Draft,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $invoice->items()->create(['description' => 'Beratung', 'quantity' => '3.00', 'unit' => 'h', 'unit_price' => '95.00', 'position' => 1]);
        $fake = $this->fake([
            self::API . 'order/602/invoice' => fn () => self::itemResponse(['invoice' => ['id' => 701], 'order' => ['id' => 602]]),
            self::API . 'order/' => fn () => self::itemResponse(['id' => '602'], 201),
            self::API . 'invoice/701/lock' => fn () => self::itemResponse(['id' => 701, 'number' => 'RE-1001', 'state' => 'locked']),
        ]);

        $this->actingAs($this->user)->post(route('invoices.issue', $invoice))->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame(['RE-1001', InvoiceStatus::Issued], [$invoice->refresh()->number, $invoice->status]);
        $fake->assertSent(fn (RequestInterface $request): bool => $request->getMethod() === 'PUT' && str_ends_with((string) $request->getUri(), '/invoice/701/lock'));
        $reference = ExternalReference::query()->where('external_type', OrgaMaxDocumentIssuer::EXT_TYPE_INVOICE)->firstOrFail();
        $this->assertSame(['602', '701', 'RE-1001'], [$reference->external_id, $reference->payload['invoice_id'] ?? null, $reference->external_number]);
    }

    public function test_credit_notes_stay_out_of_orgamax(): void {
        // orgaMAX legt per API keine Gutschrift an — der Kreis bleibt ohne Aussteller.
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::CreditNote, ['source' => OrgaMaxPlugin::ID]);
        $credit = Invoice::create([
            'organization_id' => $this->org->id,
            'customer_id' => $this->customer->id,
            'number' => NumberSequenceService::DRAFT_PREFIX . 'GUT1',
            'status' => InvoiceStatus::Draft,
            'type' => Invoice::TYPE_CREDIT_NOTE,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->user->id,
        ]);
        $credit->items()->create(['description' => 'Korrektur', 'quantity' => '-1.00', 'unit' => 'h', 'unit_price' => '95.00', 'position' => 1]);
        $fake = FakePluginHttp::fake();

        $this->actingAs($this->user)->from(route('invoices.show', $credit))->post(route('invoices.issue', $credit))
            ->assertSessionHas('error', __('numbering.issue.draft_number', ['program' => app(OrgaMaxPlugin::class)->name()]));

        $fake->assertNothingSent();
        $response = $this->actingAs($this->user)->get(route('admin.number-formats.index'))->assertOk();
        $rows = collect((array) $response->viewData('rows'))->keyBy(fn (array $row): string => $row['scope']->value);
        $this->assertArrayHasKey(OrgaMaxPlugin::ID, (array) $rows['invoice']['sourceOptions']);
        $this->assertArrayHasKey(OrgaMaxPlugin::ID, (array) $rows['quote']['sourceOptions']);
    }
}
