<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MarketplaceReconcilerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\ReconciliationStatus;
use App\Models\{Customer, ExternalReference, ForeignCustomer};
use App\Plugins\Lexoffice\{LexofficeInvoiceLineReader, LexofficePlugin};
use App\Services\Reselling\Marketplace\{ContactMapping, MarketplaceContactResolver, MarketplacePurchasesReader, MarketplaceReconciler, ReconciliationOptions};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class MarketplaceReconcilerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public const FIXTURE = __DIR__ . '/../../Fixtures/Reselling/marketplace-purchases.csv';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_reconciles_periods_against_lexoffice_invoice_lines(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster Bau GmbH',
            'company' => 'Muster Bau GmbH',
            'email' => 'max@musterbau.test',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-1',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ]);

        $fake = self::fakeLexoffice();

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $resolver = app(MarketplaceContactResolver::class);

        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $resolver->resolve($this->organization, $company, [], $source);
        }

        $this->assertSame(['c-1'], $mappings['100001']->contactIds);
        $this->assertSame(ContactMapping::SOURCE_REFERENCE, $mappings['100001']->source);
        $this->assertTrue($mappings['100001']->customer?->is($customer));
        $this->assertStringContainsString('E-Mail', $mappings['100001']->detail, 'Zuordnungsgrund wird ausgewiesen');
        $this->assertStringNotContainsString('weicht ab', $mappings['100001']->detail);
        $this->assertSame([], $resolver->errors());

        $this->assertSame(['c-2'], $mappings['100002']->contactIds, 'Namenssuche mit eindeutigem Treffer');
        $this->assertSame(ContactMapping::SOURCE_SEARCH, $mappings['100002']->source);
        $this->assertSame('Namenssuche (Name gleich)', $mappings['100002']->sourceLabel());

        $this->assertFalse($mappings['100003']->isResolved(), 'zwei Treffer → nicht raten');
        $this->assertCount(2, $mappings['100003']->candidates);

        $report = (new MarketplaceReconciler)->reconcile(
            $import->entitlements,
            $mappings,
            $source,
            new ReconciliationOptions(CarbonImmutable::parse('2025-01-01'), 45, 90),
        );

        $this->assertSame([
            'covered' => 1,
            'underpriced' => 1,
            'partial' => 0,
            'covered_by_amount' => 0,
            'missing' => 4,
            'unmapped' => 2,
        ], $report->countsByStatus());

        $muster = $report->companies[0];
        $this->assertSame('Muster Bau GmbH', $muster->company()->name);
        [$premium8, $premium1] = $muster->findings;

        $this->assertSame(ReconciliationStatus::Covered, $premium8->status);
        $this->assertSame(8, $premium8->period->quantity);
        $this->assertSame(['RE-2024-01'], $premium8->voucherNumbers());
        $this->assertSame(26000, $premium8->lowestUnitNet?->getMinorAmount());
        $this->assertSame(0, $premium8->openFee()->getMinorAmount());

        $this->assertSame(ReconciliationStatus::Underpriced, $premium1->status);
        $this->assertSame(['RE-2024-02'], $premium1->voucherNumbers());
        $this->assertSame(20000, $premium1->lowestUnitNet?->getMinorAmount());
        $this->assertStringContainsString('unter Einkauf', $premium1->note);

        $this->assertCount(1, $muster->extras, 'Exchange-Position ohne fällige Periode wird ausgewiesen');
        $this->assertSame('Exchange Online Plan 1', $muster->extras[0]->line->name);
        $this->assertSame(2.0, $muster->extras[0]->remainingQuantity);

        $beispiel = $report->companies[1];
        $this->assertCount(4, $beispiel->findings);
        foreach ($beispiel->findings as $finding) {
            $this->assertSame(ReconciliationStatus::Missing, $finding->status);
        }
        $this->assertSame(66486, $report->openFee()->getMinorAmount(), '2 × 41,55 + 2 × 290,88');

        $unbekannt = $report->companies[2];
        $this->assertCount(2, $unbekannt->findings, 'Perioden 11/2023 und 11/2024');
        $this->assertSame(ReconciliationStatus::Unmapped, $unbekannt->findings[0]->status);
        $this->assertSame(8796, $report->unmappedFee()->getMinorAmount());
        $this->assertCount(1, $report->unmappedCompanies());

        $fake->assertSent(static fn(RequestInterface $request): bool => str_contains((string) $request->getUri(), '/voucherlist')
            && str_contains((string) $request->getUri(), 'contactId=c-1')
            && str_contains((string) $request->getUri(), 'voucherDateFrom=2024-06-18'));
        $fake->assertSent(static fn(RequestInterface $request): bool => str_contains((string) $request->getUri(), '/invoices/inv-1'));
        foreach ($fake->recorded() as $recorded) {
            $this->assertStringNotContainsString('/invoices/inv-3', (string) $recorded['request']->getUri(), 'stornierte Belege werden nicht nachgeladen');
        }
    }

    public function test_email_match_to_differently_named_customer_is_flagged_and_fuzzy_only_needs_same_name(): void {
        // Besteller-Login gehört zu einer anders benannten Firma → zuordnen, aber markieren.
        $other = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Andere Firma e.K.',
            'company' => 'Andere Firma e.K.',
            'email' => 'max@musterbau.test',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-9',
            'referenceable_type' => $other->getMorphClass(),
            'referenceable_id' => $other->getKey(),
        ]);
        // Nur unscharf ähnlich, nicht namensgleich → kein Treffer, sondern Kandidat.
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispiel Logistik und Spedition',
            'company' => 'Beispiel Logistik und Spedition',
        ]);

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $resolver = app(MarketplaceContactResolver::class);

        $muster = $resolver->resolve($this->organization, $import->companies()['100001'], [], null);
        $this->assertSame(['c-9'], $muster->contactIds);
        $this->assertSame('Kunde + Verknüpfung (E-Mail · Name weicht ab)', $muster->sourceLabel());

        $beispiel = $resolver->resolve($this->organization, $import->companies()['100002'], [], null);
        $this->assertFalse($beispiel->isResolved());
        $this->assertNull($beispiel->customer, 'unscharfer Namenstreffer ohne Namensgleichheit wird nicht übernommen');
        foreach ($beispiel->candidates as $candidate) {
            $this->assertStringContainsString('Name (unscharf)', $candidate);
        }
    }

    public function test_search_errors_are_collected_not_swallowed(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['message' => 'Unauthorized'], 401),
        ]);
        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $resolver = app(MarketplaceContactResolver::class);
        $source = (new LexofficeInvoiceLineReader('bad-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();

        $mapping = $resolver->resolve($this->organization, $import->companies()['100003'], [], $source);

        $this->assertFalse($mapping->isResolved());
        $this->assertCount(1, $resolver->errors());
        $this->assertStringContainsString('401', $resolver->errors()[0]);
    }

    public function test_foreign_customers_are_reconciled_via_the_partner_with_a_shared_line_pool(): void {
        // Partner „IT-Haus GmbH" bekommt die Rechnung für zwei Endkunden.
        $partner = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'IT-Haus GmbH',
            'company' => 'IT-Haus GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-partner',
            'referenceable_type' => $partner->getMorphClass(),
            'referenceable_id' => $partner->getKey(),
        ]);
        ForeignCustomer::create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Muster Bau GmbH']);
        ForeignCustomer::create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Beispiel Logistik']);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response(['organizationId' => 'org-1']),
            'https://api.lexoffice.io/v1/voucherlist*' => static function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = ($query['contactId'] ?? '') === 'c-partner' ? [
                    ['id' => 'inv-p1', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-P-1', 'voucherDate' => '2024-09-01T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                    ['id' => 'inv-p2', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-P-2', 'voucherDate' => '2023-04-03T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                ] : [];

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
            'https://api.lexoffice.io/v1/invoices/inv-p1' => FakePluginHttp::response([
                'id' => 'inv-p1', 'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => [
                    ['type' => 'custom', 'name' => 'Microsoft 365 Business Premium', 'description' => 'Endkunde Muster Bau GmbH', 'quantity' => 9, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 250.00, 'grossAmount' => 297.50, 'taxRatePercentage' => 19]],
                ],
            ]),
            'https://api.lexoffice.io/v1/invoices/inv-p2' => FakePluginHttp::response([
                'id' => 'inv-p2', 'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => [
                    // Eine Sammelposition für beide Exchange-Positionen von Beispiel Logistik (1 + 7 Stück).
                    ['type' => 'custom', 'name' => 'Exchange Online Plan 1', 'description' => 'Beispiel Logistik, 8 Postfächer', 'quantity' => 8, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 50.00, 'grossAmount' => 59.50, 'taxRatePercentage' => 19]],
                ],
            ]),
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
        ]);

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $resolver = app(MarketplaceContactResolver::class);
        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $resolver->resolve($this->organization, $company, [], $source);
        }

        $this->assertSame(ContactMapping::SOURCE_FOREIGN, $mappings['100001']->source);
        $this->assertSame('IT-Haus GmbH', $mappings['100001']->billedVia);
        $this->assertTrue($mappings['100001']->customer?->is($partner));
        $this->assertSame(['c-partner'], $mappings['100001']->contactIds);
        $this->assertSame('Fremdkunde (über IT-Haus GmbH)', $mappings['100001']->sourceLabel());
        $this->assertSame(['c-partner'], $mappings['100002']->contactIds);

        $report = (new MarketplaceReconciler)->reconcile($import->entitlements, $mappings, $source, new ReconciliationOptions(CarbonImmutable::parse('2024-12-31'), 45, 90));

        $muster = $report->companies[0];
        [$premium8, $premium1] = $muster->findings;
        $this->assertSame(ReconciliationStatus::Covered, $premium8->status, 'acht Stück aus der Partnerposition');
        $this->assertSame(['RE-P-1'], $premium8->voucherNumbers());
        $this->assertSame(ReconciliationStatus::Covered, $premium1->status, 'die neunte Einheit derselben Position — Vorrat wird geteilt, nicht doppelt gezählt');
        $this->assertSame([], $muster->extras, 'Position voll verbraucht');

        $beispiel = $report->companies[1];
        $this->assertCount(4, $beispiel->findings);
        $covered = array_values(array_filter($beispiel->findings, static fn($f) => $f->status === ReconciliationStatus::Covered));
        $this->assertCount(2, $covered, 'Perioden 2023: 1 + 7 Stück aus der Sammelposition mit 8 Stück');
        $missing = array_values(array_filter($beispiel->findings, static fn($f) => $f->status === ReconciliationStatus::Missing));
        $this->assertCount(2, $missing, 'Perioden 2024 ohne Partnerrechnung');
        $this->assertStringContainsString('bei IT-Haus GmbH', $missing[0]->note);
    }

    public function test_manual_partner_target_maps_to_the_partner_contacts(): void {
        $partner = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'IT-Haus GmbH',
            'company' => 'IT-Haus GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-partner',
            'referenceable_type' => $partner->getMorphClass(),
            'referenceable_id' => $partner->getKey(),
        ]);
        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $resolver = app(MarketplaceContactResolver::class);

        $byName = $resolver->resolve($this->organization, $import->companies()['100003'], ['Unbekannt UG' => 'partner:IT-Haus GmbH'], null);
        $this->assertSame(['c-partner'], $byName->contactIds);
        $this->assertSame(ContactMapping::SOURCE_MANUAL, $byName->source);
        $this->assertSame('IT-Haus GmbH', $byName->billedVia);

        $bySqid = $resolver->resolve($this->organization, $import->companies()['100003'], ['100003' => 'partner:' . $partner->sqid], null);
        $this->assertSame(['c-partner'], $bySqid->contactIds);
    }

    /**
     * Produktionslauf 2026-09-03: „strcmp(): Argument #2 must be of type string,
     * int given" — numerische Firmen-Schlüssel werden als Array-Schlüssel zu int,
     * der Tiebreaker greift nur bei Perioden mit gleichem Beginn.
     */
    public function test_periods_of_different_numeric_companies_starting_on_the_same_day_do_not_crash(): void {
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
        ]);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();

        $companyA = new \App\Services\Reselling\Marketplace\MarketplaceCompany('100001', 'Firma A', null, null);
        $companyB = new \App\Services\Reselling\Marketplace\MarketplaceCompany('100002', 'Firma B', null, null);
        $make = static fn(\App\Services\Reselling\Marketplace\MarketplaceCompany $company, string $id): \App\Services\Reselling\Marketplace\MarketplaceEntitlement => new \App\Services\Reselling\Marketplace\MarketplaceEntitlement(
            company: $company,
            entitlementId: $id,
            orderId: $id,
            application: 'Microsoft Exchange Online',
            edition: 'Exchange Online (Plan 1)',
            fee: \CommonToolkit\ValueObjects\Money::of('41.55', \CommonToolkit\Enums\CurrencyCode::Euro),
            frequency: \App\Enums\Reselling\BillingFrequency::Yearly,
            startsOn: CarbonImmutable::parse('2023-04-01'),
            endsOn: CarbonImmutable::parse('2027-04-06'),
            status: 'CANCELLED',
            assignedUsers: 0,
            sourceLine: 2,
        );
        $entitlements = [$make($companyA, 'a-1'), $make($companyB, 'b-1'), $make($companyA, 'a-2')];
        $mappings = [
            '100001' => new ContactMapping($companyA, null, ['c-a'], ContactMapping::SOURCE_MANUAL),
            '100002' => new ContactMapping($companyB, null, ['c-b'], ContactMapping::SOURCE_MANUAL),
        ];

        $report = (new MarketplaceReconciler)->reconcile($entitlements, $mappings, $source, new ReconciliationOptions(CarbonImmutable::parse('2026-09-03')));

        $this->assertCount(2, $report->companies);
        $this->assertCount(8, $report->companies[0]->findings, 'Firma A: zwei Positionen × vier Perioden');
        $this->assertCount(4, $report->companies[1]->findings);
        $this->assertSame(12, $report->countsByStatus()['missing']);
    }

    /**
     * Betreiber 2026-09-03: „auf den Rechnungen steht bei LDS meistens im Text der
     * Rechnung, welcher Fremdkunde das ist, nicht in den Positionen". Eine offene
     * Firma wird über die Belegtexte der Partner zugeordnet; die Position ohne
     * Editionsnennung deckt die Periode tolerant, streng bleibt sie „Fehlt".
     */
    public function test_open_company_is_resolved_via_partner_invoice_text_and_generic_lines_count_tolerantly(): void {
        $partner = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'LDS Systems GmbH',
            'company' => 'LDS Systems GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-lds',
            'referenceable_type' => $partner->getMorphClass(),
            'referenceable_id' => $partner->getKey(),
        ]);
        // Der Partner ist als solcher bekannt: er hat bereits einen (anderen) Fremdkunden.
        ForeignCustomer::create(['organization_id' => $this->organization->id, 'customer_id' => $partner->id, 'name' => 'Irgendwer AG']);

        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response(['organizationId' => 'org-1']),
            'https://api.lexoffice.io/v1/voucherlist*' => static function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = ($query['contactId'] ?? '') === 'c-lds' ? [
                    ['id' => 'inv-lds', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-LDS-1', 'voucherDate' => '2023-12-01T00:00:00.000+01:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                ] : [];

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
            'https://api.lexoffice.io/v1/invoices/inv-lds' => FakePluginHttp::response([
                'id' => 'inv-lds',
                'title' => 'Rechnung',
                'introduction' => 'Microsoft-Lizenzen für Ihren Kunden Unbekannt UG, Laufzeit 11/2023 bis 10/2024',
                'remark' => 'Vielen Dank.',
                'address' => ['name' => 'LDS Systems GmbH'],
                'taxConditions' => ['taxType' => 'net'],
                'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => [
                    // Allgemeiner Text ohne Edition — tolerant zählt er, streng nicht.
                    ['type' => 'custom', 'name' => 'Microsoft 365 Lizenzen', 'description' => 'Jahresabrechnung', 'quantity' => 1, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 60.00, 'grossAmount' => 71.40, 'taxRatePercentage' => 19]],
                ],
            ]),
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
        ]);

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $resolver = app(MarketplaceContactResolver::class);
        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $resolver->resolve($this->organization, $company, [], $source);
        }
        $this->assertFalse($mappings['100003']->isResolved(), 'Unbekannt UG: kein Kunde, kein Fremdkunden-Stammsatz, kein Lexoffice-Treffer');

        $options = new ReconciliationOptions(CarbonImmutable::parse('2024-06-01'), 45, 90);
        $pool = new \App\Services\Reselling\Marketplace\InvoiceLinePool($source);
        [$from, $to] = \App\Services\Reselling\Marketplace\ReconciliationRunner::globalWindow($import, $options);
        $partners = \App\Services\Reselling\Marketplace\ReconciliationRunner::partnerContacts($this->organization, $mappings);
        $this->assertArrayHasKey('c-lds', $partners, 'Kunde mit Fremdkunden gehört in den Partner-Pool');

        $mappings = (new \App\Services\Reselling\Marketplace\ForeignCustomerTextResolver)->resolve($mappings, $import->companies(), $pool, array_keys($partners), $from, $to, $partners);

        $unbekannt = $mappings['100003'];
        $this->assertTrue($unbekannt->isResolved());
        $this->assertSame(ContactMapping::SOURCE_INVOICE_TEXT, $unbekannt->source);
        $this->assertSame(['c-lds'], $unbekannt->contactIds);
        $this->assertSame('LDS Systems GmbH', $unbekannt->billedVia);
        $this->assertTrue($unbekannt->customer?->is($partner));

        // Tolerant: die allgemeine Microsoft-Position deckt die Teams-Periode 11/2023.
        $report = (new MarketplaceReconciler)->reconcile($import->entitlements, $mappings, $source, $options, $pool);
        $company = collect($report->companies)->first(static fn($c) => $c->company()->key === '100003');
        $this->assertNotNull($company);
        $this->assertSame(ReconciliationStatus::Covered, $company->findings[0]->status);
        $this->assertStringContainsString('Produkt nur allgemein erkannt: Microsoft 365 Lizenzen', $company->findings[0]->note);
        $this->assertFalse($company->findings[0]->matches[0]['exact']);
        $this->assertNotEmpty($company->lines, 'Diagnosezeilen enthalten die gesehene Position');
        $this->assertSame('LDS Systems GmbH', $company->lines[0]['line']->recipient);

        // Streng: ohne Editionsnennung bleibt die Periode offen.
        $strict = new ReconciliationOptions(CarbonImmutable::parse('2024-06-01'), 45, 90, true);
        $reportStrict = (new MarketplaceReconciler)->reconcile($import->entitlements, $mappings, $source, $strict, new \App\Services\Reselling\Marketplace\InvoiceLinePool($source));
        $companyStrict = collect($reportStrict->companies)->first(static fn($c) => $c->company()->key === '100003');
        $this->assertSame(ReconciliationStatus::CoveredByAmount, $companyStrict->findings[0]->status, 'streng: Beleg im Fenster mit ausreichendem Betrag, Produkt nicht erkannt');
    }

    /**
     * Produktivbericht 2026-09-03: Positionen sind Monatspreise mit Menge in
     * Monaten — „Business Premium 12 × 20,60 €" ist EINE Lizenz für ein Jahr,
     * nicht zwölf Lizenzen unter Einkauf. Gerechnet wird in Lizenzmonaten.
     */
    public function test_monthly_priced_lines_are_counted_in_license_months(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster Bau GmbH',
            'company' => 'Muster Bau GmbH',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-1',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ]);
        $monthly = static fn(string $name, float $months, float $unit): array => ['type' => 'custom', 'name' => $name, 'description' => 'Monatspreis', 'quantity' => $months, 'unitName' => 'Monat', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => $unit, 'grossAmount' => round($unit * 1.19, 2), 'taxRatePercentage' => 19]];
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response(['organizationId' => 'org-1']),
            'https://api.lexoffice.io/v1/voucherlist*' => static function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = ($query['contactId'] ?? '') === 'c-1' ? [
                    ['id' => 'inv-a', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-A', 'voucherDate' => '2024-08-05T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                    ['id' => 'inv-b', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-B', 'voucherDate' => '2024-10-10T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                ] : [];

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
            // Acht Zeilen à 12 Monate = acht Lizenzen für ein Jahr (Periode mit Menge 8).
            'https://api.lexoffice.io/v1/invoices/inv-a' => FakePluginHttp::response([
                'id' => 'inv-a', 'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => array_map(static fn(int $i): array => $monthly('Microsoft 365 Business Premium', 12, 20.60), range(1, 8)),
            ]),
            // Eine Lizenz anteilig: 4 + 8 Monate — deckt die Periode mit Menge 1 vollständig.
            'https://api.lexoffice.io/v1/invoices/inv-b' => FakePluginHttp::response([
                'id' => 'inv-b', 'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                'lineItems' => [$monthly('Microsoft 365 Business Premium', 4, 20.60), $monthly('Microsoft 365 Business Premium', 8, 20.60)],
            ]),
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
        ]);

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $resolver = app(MarketplaceContactResolver::class);
        $mappings = [];
        foreach ($import->companies() as $key => $company) {
            $mappings[$key] = $resolver->resolve($this->organization, $company, [], $source);
        }

        $report = (new MarketplaceReconciler)->reconcile($import->entitlements, $mappings, $source, new ReconciliationOptions(CarbonImmutable::parse('2025-01-01'), 45, 90));
        [$premium8, $premium1] = $report->companies[0]->findings;

        $this->assertSame(ReconciliationStatus::Covered, $premium8->status, '8 × 12 Monate = 96 Lizenzmonate für 8 Lizenzen');
        $this->assertSame(24720, $premium8->lowestUnitNet?->getMinorAmount(), '20,60 €/Monat aufs Jahr = 247,20 € ≥ Einkauf 244,76 €');
        $this->assertCount(8, $premium8->matches);
        $this->assertTrue($premium8->matches[0]['monthly']);
        $this->assertSame(12.0, $premium8->matches[0]['months']);

        $this->assertSame(ReconciliationStatus::Covered, $premium1->status, '4 + 8 Monate = eine Lizenz für ein Jahr');
        $this->assertCount(2, $premium1->matches);
        $this->assertSame(0.0, $premium1->uncoveredQuantity);
        $this->assertSame([], $report->companies[0]->extras, 'alle Monate verbraucht');
    }

    public function test_monthly_line_for_one_license_covers_only_half_of_a_two_license_period(): void {
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Beispiel Logistik',
            'company' => 'Beispiel Logistik',
        ]);
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => LexofficePlugin::ID,
            'external_type' => LexofficePlugin::EXT_TYPE_CONTACT,
            'external_id' => 'c-2',
            'referenceable_type' => $customer->getMorphClass(),
            'referenceable_id' => $customer->getKey(),
        ]);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/voucherlist*' => static function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = ($query['contactId'] ?? '') === 'c-2' ? [
                    ['id' => 'inv-x', 'voucherType' => 'invoice', 'voucherStatus' => 'paid', 'voucherNumber' => 'RE-X', 'voucherDate' => '2023-04-01T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false],
                ] : [];

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
            'https://api.lexoffice.io/v1/invoices/inv-x' => FakePluginHttp::response([
                'id' => 'inv-x', 'taxConditions' => ['taxType' => 'net'], 'totalPrice' => ['currency' => 'EUR'],
                // 12 Monate zu 3,95 €: eine Exchange-Lizenz für ein Jahr; die 7er-Position bleibt offen.
                'lineItems' => [['type' => 'custom', 'name' => 'Exchange Online (Plan 1)', 'description' => '', 'quantity' => 12, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 3.95, 'grossAmount' => 4.70, 'taxRatePercentage' => 19]]],
            ]),
            'https://api.lexoffice.io/v1/contacts*' => FakePluginHttp::response(['content' => [], 'totalPages' => 1]),
        ]);

        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $source = (new LexofficeInvoiceLineReader('lex-key', 'https://api.lexoffice.io/v1'))->withoutThrottle();
        $mappings = ['100002' => new ContactMapping($import->companies()['100002'], $customer, ['c-2'], ContactMapping::SOURCE_REFERENCE)];
        $entitlements = array_values(array_filter($import->entitlements, static fn($e) => $e->company->key === '100002'));

        $report = (new MarketplaceReconciler)->reconcile($entitlements, $mappings, $source, new ReconciliationOptions(CarbonImmutable::parse('2023-12-31'), 45, 90));
        $findings = $report->companies[0]->findings;
        $this->assertCount(2, $findings);
        // Periode 28.03.23 (1 Lizenz) zuerst: gedeckt mit 12 Monaten, Jahresstückpreis 47,40 € ≥ 41,55 €.
        $this->assertSame(ReconciliationStatus::Covered, $findings[0]->status);
        $this->assertSame(4740, $findings[0]->lowestUnitNet?->getMinorAmount());
        // Periode 30.03.23 (7 Lizenzen): nichts mehr übrig → fehlt, offene Gebühr voll.
        $this->assertSame(ReconciliationStatus::Missing, $findings[1]->status);
        $this->assertSame(29088, $findings[1]->openFee()->getMinorAmount());
    }

    public function test_manual_map_wins_over_matching(): void {
        self::fakeLexoffice();
        $import = (new MarketplacePurchasesReader)->read(self::FIXTURE);
        $resolver = app(MarketplaceContactResolver::class);

        $mapping = $resolver->resolve($this->organization, $import->companies()['100003'], [
            'Unbekannt UG' => '11111111-2222-3333-4444-555555555555',
        ], null);

        $this->assertSame(['11111111-2222-3333-4444-555555555555'], $mapping->contactIds);
        $this->assertSame(ContactMapping::SOURCE_MANUAL, $mapping->source);
    }

    public static function fakeLexoffice(): FakePluginHttp {
        $invoice = static fn(string $id, string $number, string $date, string $status = 'paid'): array => [
            'id' => $id, 'voucherType' => 'invoice', 'voucherStatus' => $status, 'voucherNumber' => $number,
            'voucherDate' => $date . 'T00:00:00.000+02:00', 'totalAmount' => 0, 'currency' => 'EUR', 'archived' => false,
        ];

        return FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response(['organizationId' => 'org-1', 'companyName' => 'Test Reseller']),
            'https://api.lexoffice.io/v1/voucherlist*' => static function (RequestInterface $request) use ($invoice) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = match ($query['contactId'] ?? '') {
                    'c-1' => [
                        $invoice('inv-1', 'RE-2024-01', '2024-08-05'),
                        $invoice('inv-2', 'RE-2024-02', '2024-10-10'),
                        $invoice('inv-3', 'RE-2024-03', '2024-10-12', 'voided'),
                    ],
                    default => [],
                };

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
            'https://api.lexoffice.io/v1/invoices/inv-1' => FakePluginHttp::response([
                'id' => 'inv-1',
                'taxConditions' => ['taxType' => 'net'],
                'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 2180.00],
                'lineItems' => [
                    ['type' => 'custom', 'name' => 'Microsoft 365 Business Premium', 'description' => 'Jahreslizenz 08/2024 – 07/2025', 'quantity' => 8, 'unitName' => 'Stück', 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 260.00, 'grossAmount' => 309.40, 'taxRatePercentage' => 19]],
                    ['type' => 'custom', 'name' => 'Exchange Online Plan 1', 'description' => '', 'quantity' => 2, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 50.00, 'grossAmount' => 59.50, 'taxRatePercentage' => 19]],
                    ['type' => 'text', 'name' => 'Hinweis', 'description' => 'Zahlbar innerhalb von 14 Tagen'],
                ],
            ]),
            'https://api.lexoffice.io/v1/invoices/inv-2' => FakePluginHttp::response([
                'id' => 'inv-2',
                'taxConditions' => ['taxType' => 'net'],
                'totalPrice' => ['currency' => 'EUR', 'totalNetAmount' => 200.00],
                'lineItems' => [
                    ['type' => 'custom', 'name' => 'M365 Business Premium', 'description' => 'Zusatzlizenz', 'quantity' => 1, 'unitPrice' => ['currency' => 'EUR', 'netAmount' => 200.00, 'grossAmount' => 238.00, 'taxRatePercentage' => 19]],
                ],
            ]),
            'https://api.lexoffice.io/v1/contacts*' => static function (RequestInterface $request) {
                parse_str($request->getUri()->getQuery(), $query);
                $content = match ($query['name'] ?? '') {
                    'Beispiel Logistik' => [['id' => 'c-2', 'company' => ['name' => 'Beispiel Logistik']]],
                    'Unbekannt UG' => [
                        ['id' => 'c-3', 'company' => ['name' => 'Unbekannt UG Nord']],
                        ['id' => 'c-4', 'person' => ['firstName' => 'Uwe', 'lastName' => 'Unbekannt']],
                    ],
                    default => [],
                };

                return FakePluginHttp::response(['content' => $content, 'totalPages' => 1]);
            },
        ]);
    }
}
