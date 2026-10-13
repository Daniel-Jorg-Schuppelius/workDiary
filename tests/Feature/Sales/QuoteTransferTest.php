<?php
/*
 * Created on   : Tue Oct 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QuoteTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Sales;

use App\Enums\Numbering\NumberScope;
use App\Enums\Sales\QuoteStatus;
use App\Models\Customer\Customer;
use App\Models\Integration\ExternalReference;
use App\Models\Platform\{Organization, PluginSetting, User};
use App\Models\Sales\Quote;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillDocumentIssuer;
use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\Lexoffice\Services\LexofficeQuotationService;
use App\Services\Invoicing\QuoteService;
use App\Services\Numbering\{NumberAuthority, NumberSequenceService};
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Phase 145, P2 (MVP-1160/1161): Angebot schreibt workDiary, das Programm vergibt die Nummer beim Versand. */
final class QuoteTransferTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private Customer $customer;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->user = User::factory()->buchhaltung()->create(['organization_id' => $this->org->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->org->id, 'name' => 'Werft Nord GmbH']);
    }

    private function enable(string $pluginId): void {
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'enabled' => true, 'settings' => ['api_key' => 'k']]);
        ExternalReference::create([
            'organization_id' => $this->org->id, 'plugin_id' => $pluginId, 'external_type' => ExternalReference::TYPE_CONTACT,
            'referenceable_type' => $this->customer->getMorphClass(), 'referenceable_id' => $this->customer->id,
            'external_id' => $pluginId === LexofficePlugin::ID ? 'lex-contact-1' : '501',
        ]);
        app(NumberSequenceService::class)->setFormat($this->org, NumberScope::Quote, ['source' => $pluginId]);
    }

    private function approvedQuote(): Quote {
        $service = app(QuoteService::class);
        $quote = $service->create([
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addWeeks(2)->toDateString(),
            'terms' => 'Zahlbar nach Annahme.',
        ], [
            ['description' => 'Grundpaket', 'quantity' => 2, 'unit_price' => 500, 'tax_rate' => '19.00', 'discount_percent' => '10.00'],
            ['description' => 'Schulung (optional)', 'quantity' => 1, 'unit_price' => 300, 'tax_rate' => '19.00', 'optional' => true],
        ], $this->user);

        return $service->approve($quote, $this->user);
    }

    /** @param array<string, mixed> $body */
    private static function reply(array $body, int $status = 200): \Closure {
        return static fn (): Psr7Response => FakePluginHttp::response($body, $status);
    }

    public function test_lexoffice_numbers_the_quote_when_it_is_sent(): void {
        $this->enable(LexofficePlugin::ID);
        $quote = $this->approvedQuote();
        $this->assertTrue(NumberSequenceService::isDraft($quote->number));
        $fake = FakePluginHttp::fake([
            'https://api.lexware.io/v1/quotations?finalize=true' => self::reply(['id' => 'lex-q-9'], 201),
            'https://api.lexware.io/v1/quotations/lex-q-9' => self::reply(['id' => 'lex-q-9', 'voucherNumber' => 'AG0007']),
        ]);

        ['quote' => $sent] = app(QuoteService::class)->send($quote, $this->user);

        $this->assertSame('AG0007', $sent->number);
        $this->assertSame(QuoteStatus::Sent, $sent->status);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST'
                && data_get($body, 'address.contactId') === 'lex-contact-1'
                && data_get($body, 'lineItems.0.discountPercentage') === 10
                && data_get($body, 'lineItems.1.optional') === true
                && data_get($body, 'taxConditions.taxType') === 'net'
                && data_get($body, 'remark') === 'Zahlbar nach Annahme.'
                && data_get($body, 'totalPrice.totalDiscountPercentage') === 0;
        });
        $reference = ExternalReference::query()->where('external_type', LexofficeQuotationService::EXT_TYPE_QUOTATION)->firstOrFail();
        $this->assertSame(['lex-q-9', 'AG0007'], [$reference->external_id, $reference->external_number]);
    }

    public function test_a_new_version_becomes_a_new_quotation(): void {
        $this->enable(LexofficePlugin::ID);
        $quote = $this->approvedQuote();
        FakePluginHttp::fake([
            'https://api.lexware.io/v1/quotations?finalize=true' => [FakePluginHttp::response(['id' => 'lex-q-1'], 201), FakePluginHttp::response(['id' => 'lex-q-2'], 201)],
            'https://api.lexware.io/v1/quotations/lex-q-1' => self::reply(['voucherNumber' => 'AG0001']),
            'https://api.lexware.io/v1/quotations/lex-q-2' => self::reply(['voucherNumber' => 'AG0002']),
        ]);
        $service = app(QuoteService::class);
        ['quote' => $first] = $service->send($quote, $this->user);

        $next = $service->newVersion($first, $this->user);
        $this->assertTrue(NumberSequenceService::isDraft($next->number));
        ['quote' => $second] = $service->send($service->approve($next, $this->user), $this->user);

        $this->assertSame(['AG0001', 'AG0002'], [$first->number, $second->number]);
    }

    public function test_failed_transfer_keeps_the_quote_unsent(): void {
        $this->enable(LexofficePlugin::ID);
        $quote = $this->approvedQuote();
        FakePluginHttp::fake(['https://api.lexware.io/v1/quotations?finalize=true' => self::reply(['message' => 'kaputt'], 500)]);

        try {
            app(QuoteService::class)->send($quote, $this->user);
            $this->fail('Versand trotz gescheiterter Übertragung.');
        } catch (\RuntimeException) {
        }

        $quote->refresh();
        $this->assertSame(QuoteStatus::Approved, $quote->status);
        $this->assertTrue(NumberSequenceService::isDraft($quote->number));
    }

    public function test_easybill_numbers_the_quote_when_it_is_finished(): void {
        $this->enable(EasybillPlugin::ID);
        $quote = $this->approvedQuote();
        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/customers/501' => self::reply(['id' => 501, 'number' => '20001']),
            'https://api.easybill.de/rest/v1/documents/7001/done' => self::reply(['id' => 7001, 'number' => 'AN-2026-14', 'is_draft' => false]),
            'https://api.easybill.de/rest/v1/documents' => self::reply(['id' => 7001, 'is_draft' => true], 201),
        ]);

        ['quote' => $sent] = app(QuoteService::class)->send($quote, $this->user);

        $this->assertSame('AN-2026-14', $sent->number);
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = json_decode((string) $request->getBody(), true);

            return $request->getMethod() === 'POST' && str_ends_with($request->getUri()->getPath(), '/documents')
                && data_get($body, 'type') === 'OFFER'
                && data_get($body, 'items.0.single_price_net') === 50000
                && data_get($body, 'items.0.discount_type') === 'PERCENT'
                && data_get($body, 'items.1.type') === 'POSITION_NOCALC';
        });
        $this->assertSame('AN-2026-14', ExternalReference::query()->where('external_type', EasybillDocumentIssuer::EXT_TYPE_OFFER)->value('external_number'));
    }

    public function test_quote_scope_offers_programs_that_number_quotes(): void {
        $this->enable(LexofficePlugin::ID);
        PluginSetting::create(['organization_id' => $this->org->id, 'plugin_id' => EasybillPlugin::ID, 'enabled' => true, 'settings' => ['api_key' => 'k']]);

        $options = app(NumberAuthority::class)->sourceOptions($this->org, NumberScope::Quote);

        $this->assertEqualsCanonicalizing([NumberAuthority::SOURCE_LOCAL, LexofficePlugin::ID, EasybillPlugin::ID], array_keys($options));
    }
}
