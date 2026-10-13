<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderConfirmationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sales;

use App\Enums\Billing\DocumentRelationKind;
use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\OrderConfirmationStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Sales\{OrderConfirmation, Quote};
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillDocumentIssuer;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Services\Billing\DocumentRelations;
use App\Services\Invoicing\QuoteService;
use App\Services\Numbering\NumberSequenceService;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145, P3 (MVP-1162/1164): Auftragsbestätigung als eigener Beleg. */
final class OrderConfirmationTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-10-13 10:00:00');
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH']);
    }

    private function acceptedQuote(): Quote {
        $service = app(QuoteService::class);
        $quote = $service->create(['customer_id' => $this->customer->id, 'valid_until' => now()->addWeeks(2)->toDateString()], [
            ['description' => 'Grundpaket', 'quantity' => 2, 'unit_price' => 500, 'tax_rate' => '19.00'],
            ['description' => 'Schulung (optional)', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => '19.00', 'optional' => true],
        ], $this->user);
        $quote = $service->approve($quote, $this->user);
        ['quote' => $quote, 'acceptance_token' => $token] = $service->send($quote, $this->user);

        return $service->accept($quote, null, $token);
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status = 200): \Closure {
        return static fn (): Psr7Response => FakePluginHttp::response($body, $status);
    }

    public function test_quote_becomes_an_order_confirmation_with_its_own_number(): void {
        $quote = $this->acceptedQuote();

        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote))->assertRedirect();
        $confirmation = OrderConfirmation::query()->where('quote_id', $quote->id)->firstOrFail();

        $this->assertSame(OrderConfirmationStatus::Draft, $confirmation->status);
        $this->assertSame(['Grundpaket'], $confirmation->items->pluck('description')->all(), 'Nur angenommene Positionen.');
        $this->assertSame(1000.0, $confirmation->subtotal?->toFloat());

        $this->actingAs($this->user)->post(route('order-confirmations.confirm', $confirmation))->assertRedirect(route('order-confirmations.show', $confirmation));
        $confirmation->refresh();
        $this->assertSame(OrderConfirmationStatus::Confirmed, $confirmation->status);
        $this->assertSame('AB-2026-0001', $confirmation->number);

        $this->actingAs($this->user)->get(route('order-confirmations.show', $confirmation))->assertOk()->assertSee('AB-2026-0001');
        $pdf = $this->actingAs($this->user)->get(route('order-confirmations.pdf', $confirmation));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $pdf->getContent());
        $this->actingAs($this->user)->get(route('quotes.order-confirmation', $quote))->assertRedirect(route('order-confirmations.pdf', $confirmation));
        $this->actingAs($this->user)->get(route('quotes.show', $quote))->assertOk()->assertSee(__('quotes.order_confirmation.open', ['number' => 'AB-2026-0001']));

        // Ein zweites Erstellen öffnet dieselbe AB.
        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote))->assertRedirect(route('order-confirmations.show', $confirmation));
        $this->assertSame(1, OrderConfirmation::query()->count());

        $relations = collect(app(DocumentRelations::class)->of($quote))->keyBy(fn (array $r): string => (string) $r['node']->number);
        $this->assertSame(DocumentRelationKind::FollowUp, $relations['AB-2026-0001']['kind'] ?? null);
    }

    public function test_lexoffice_numbers_the_order_confirmation_without_predecessor(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => LexofficePlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => 'lex-contact-1']);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::OrderConfirmation, ['source' => LexofficePlugin::ID]);
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote));
        $confirmation = OrderConfirmation::query()->firstOrFail();
        $this->assertTrue(NumberSequenceService::isDraft($confirmation->number));
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/order-confirmations?finalize=true' => self::reply(['id' => 'lex-oc-5'], 201),
            'https://api.lexware.io/v1/order-confirmations/lex-oc-5' => self::reply(['voucherNumber' => 'AB0005']),
        ]);

        $this->actingAs($this->user)->post(route('order-confirmations.confirm', $confirmation))->assertSessionHasNoErrors();

        $this->assertSame('AB0005', $confirmation->refresh()->number);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && ! str_contains((string) $request->getUri(), 'precedingSalesVoucherId')
                && data_get($body, 'shippingConditions.shippingType') === 'none'
                && count((array) data_get($body, 'lineItems')) === 1;
        });
    }

    public function test_easybill_order_refers_to_the_transferred_offer(): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id, 'external_id' => '501']);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::OrderConfirmation, ['source' => EasybillPlugin::ID]);
        $quote = $this->acceptedQuote();
        ExternalReference::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'external_type' => EasybillDocumentIssuer::EXT_TYPE_OFFER,
            'referenceable_type' => $quote->getMorphClass(), 'referenceable_id' => $quote->id, 'external_id' => '7001', 'external_number' => 'AN-1']);
        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote));
        $confirmation = OrderConfirmation::query()->firstOrFail();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501]),
            'https://api.easybill.de/rest/v1/documents/7002/done' => self::reply(['id' => 7002, 'number' => 'AB-EB-3']),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 7002], 201),
        ]);

        $this->actingAs($this->user)->post(route('order-confirmations.confirm', $confirmation))->assertSessionHasNoErrors();

        $this->assertSame('AB-EB-3', $confirmation->refresh()->number);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && data_get($body, 'type') === 'ORDER' && data_get($body, 'ref_id') === 7001;
        });
    }

    public function test_feed_lists_the_order_confirmation(): void {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote));
        $confirmation = OrderConfirmation::query()->firstOrFail();
        $this->actingAs($this->user)->post(route('order-confirmations.confirm', $confirmation));

        $this->actingAs($this->user)
            ->withSession(['ui.daterange.preset' => 'custom', 'ui.daterange.from' => '2026-10-01', 'ui.daterange.to' => '2026-10-31'])
            ->get(route('billing.feed', ['tab' => 'other']))
            ->assertOk()
            ->assertSee('AB-2026-0001');
    }

    public function test_mail_dialog_and_cancel(): void {
        $quote = $this->acceptedQuote();
        $this->actingAs($this->user)->post(route('quotes.order-confirmation.store', $quote));
        $confirmation = OrderConfirmation::query()->firstOrFail();

        $this->actingAs($this->user)->get(route('order-confirmations.mail.form', $confirmation))->assertStatus(422);
        $this->actingAs($this->user)->post(route('order-confirmations.confirm', $confirmation));
        $this->actingAs($this->user)->get(route('order-confirmations.mail.form', $confirmation))->assertOk();

        $this->actingAs($this->user)->post(route('order-confirmations.cancel', $confirmation))->assertRedirect();
        $this->assertSame(OrderConfirmationStatus::Cancelled, $confirmation->refresh()->status);
    }
}
