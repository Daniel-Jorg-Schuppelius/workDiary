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
use App\Models\{Customer, ExternalReference};
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
