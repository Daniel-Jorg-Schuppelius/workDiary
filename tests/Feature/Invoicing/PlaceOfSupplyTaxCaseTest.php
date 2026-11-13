<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PlaceOfSupplyTaxCaseTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceStatus, PlaceOfSupplyKind, TaxCaseAlignment, XRechnungSyntax};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Accounting\Posting\Adapters\SalesInvoiceAdapter;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueException, InvoiceIssueService, PlaceOfSupply, TaxResolver};
use App\Settings\{SettingScope, SettingsRegistry};
use Carbon\CarbonImmutable;
use ERechnungToolkit\Parsers\ERechnungParser;
use ERechnungToolkit\Validators\KositValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1688: Leistungsort nach § 3a UStG. Grundstücksleistungen am Ort des
 * Grundstücks für alle Kunden (Abs. 3 Nr. 1), Katalogleistungen an
 * Verbraucher im Drittland nicht steuerbar (Abs. 4), übrige Leistungen an
 * Verbraucher im Drittland mit deutscher Steuer (Abs. 1); Unternehmen, Waren
 * und der Bestand ohne Pflege unverändert.
 */
final class PlaceOfSupplyTaxCaseTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Beispieltechnik GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@beispieltechnik.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Beispieltechnik GmbH',
        ]]]);
        $this->admin = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->admin);
    }

    /**
     * Die Matrix: Empfänger × Land × Leistungsort-Art bzw. Ware. Erwartet: Kategorie, Satz, Reverse Charge.
     *
     * @return iterable<string, array{0: array<string, mixed>, 1: ?PlaceOfSupply, 2: string, 3: string, 4: string, 5: bool}>
     */
    public static function matrix(): iterable {
        $company = ['is_consumer' => false];
        $consumer = ['is_consumer' => true];
        $general = new PlaceOfSupply(PlaceOfSupplyKind::General);
        $catalog = new PlaceOfSupply(PlaceOfSupplyKind::Catalog);
        $propertyHere = new PlaceOfSupply(PlaceOfSupplyKind::Property);
        $propertyAt = new PlaceOfSupply(PlaceOfSupplyKind::Property, 'at');
        $propertyCh = new PlaceOfSupply(PlaceOfSupplyKind::Property, 'CH');

        foreach (['Unternehmen' => $company, 'Verbraucher' => $consumer] as $who => $flag) {
            // Inland: immer deutsche Steuer, nur ein Grundstück im Ausland ist nicht steuerbar.
            yield "$who Inland allgemein" => [['country' => 'DE'] + $flag, $general, 'services', 'S', '19.00', false];
            yield "$who Inland Katalog" => [['country' => 'DE'] + $flag, $catalog, 'services', 'S', '19.00', false];
            yield "$who Inland Grundstück Inland" => [['country' => 'DE'] + $flag, $propertyHere, 'services', 'S', '19.00', false];
            yield "$who Inland Grundstück Ausland" => [['country' => 'DE'] + $flag, $propertyAt, 'services', 'O', '0.00', false];
            yield "$who Inland Ware" => [['country' => 'DE'] + $flag, $catalog, 'goods', 'S', '19.00', false];
            // Grundstück im Inland: deutsche Steuer auch für Kunden im Ausland.
            yield "$who EU Grundstück Inland" => [['country' => 'AT', 'vat_id' => 'ATU13585627'] + $flag, $propertyHere, 'services', 'S', '19.00', false];
            yield "$who EU Grundstück am Ort" => [['country' => 'AT', 'vat_id' => 'ATU13585627'] + $flag, $propertyAt, 'services', 'O', '0.00', false];
            yield "$who Drittland Grundstück Inland" => [['country' => 'CH'] + $flag, $propertyHere, 'services', 'S', '19.00', false];
            yield "$who Drittland Grundstück am Ort" => [['country' => 'CH'] + $flag, $propertyCh, 'services', 'O', '0.00', false];
            yield "$who Drittland Ware" => [['country' => 'CH'] + $flag, $catalog, 'goods', 'G', '0.00', false];
        }

        // Unternehmen wie bisher: EU mit USt-IdNr. Reverse Charge, Drittland nicht steuerbar — gleich welche Art.
        yield 'Unternehmen EU allgemein' => [['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_consumer' => false], $general, 'services', 'AE', '0.00', true];
        yield 'Unternehmen EU Katalog' => [['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_consumer' => false], $catalog, 'services', 'AE', '0.00', true];
        yield 'Unternehmen EU Ware' => [['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_consumer' => false], $general, 'goods', 'AE', '0.00', true];
        yield 'Unternehmen Drittland allgemein' => [['country' => 'CH', 'is_consumer' => false], $general, 'services', 'O', '0.00', false];
        yield 'Unternehmen Drittland Katalog' => [['country' => 'CH', 'is_consumer' => false], $catalog, 'services', 'O', '0.00', false];

        // Verbraucher: EU nach dem Satz des Verkäufers (auch mit USt-IdNr.), Drittland nach Art.
        yield 'Verbraucher EU allgemein' => [['country' => 'AT', 'is_consumer' => true], $general, 'services', 'S', '19.00', false];
        yield 'Verbraucher EU Katalog' => [['country' => 'AT', 'is_consumer' => true], $catalog, 'services', 'S', '19.00', false];
        yield 'Verbraucher EU mit USt-IdNr.' => [['country' => 'AT', 'vat_id' => 'ATU13585627', 'is_consumer' => true], $general, 'services', 'S', '19.00', false];
        yield 'Verbraucher Drittland allgemein' => [['country' => 'CH', 'is_consumer' => true], $general, 'services', 'S', '19.00', false];
        yield 'Verbraucher Drittland Katalog' => [['country' => 'CH', 'is_consumer' => true], $catalog, 'services', 'O', '0.00', false];

        // Inlands-§ 13b bleibt, auch bei der Grundstücksleistung; ein Verbraucher schuldet die Steuer nie.
        yield '§ 13b Grundstück Inland' => [['country' => 'DE', 'is_reverse_charge_recipient' => true], $propertyHere, 'services', 'AE', '0.00', true];
        yield '§ 13b allgemein' => [['country' => 'DE', 'is_reverse_charge_recipient' => true], $general, 'services', 'AE', '0.00', true];
        yield '§ 13b und Verbraucher' => [['country' => 'DE', 'is_reverse_charge_recipient' => true, 'is_consumer' => true], $propertyHere, 'services', 'S', '19.00', false];
    }

    /** @param array<string, mixed> $customer */
    #[DataProvider('matrix')]
    public function test_tax_case_follows_recipient_and_place_of_supply(array $customer, ?PlaceOfSupply $place, string $category, string $expected, string $rate, bool $reverseCharge): void {
        $tax = app(TaxResolver::class)->resolve($this->organization, $this->customer($customer), CarbonImmutable::parse('2030-04-15'), $category, $place);

        $this->assertSame([$expected, $rate, $reverseCharge], [$tax['category'], $tax['rate'], $tax['reverse_charge']]);
    }

    public function test_out_of_scope_cases_carry_their_own_note(): void {
        $resolver = app(TaxResolver::class);

        $catalog = $resolver->resolve($this->organization, $this->customer(['country' => 'CH', 'is_consumer' => true]), null, 'services', new PlaceOfSupply(PlaceOfSupplyKind::Catalog));
        $property = $resolver->resolve($this->organization, $this->customer(['country' => 'DE']), null, 'services', new PlaceOfSupply(PlaceOfSupplyKind::Property, 'FR'));
        $company = $resolver->resolve($this->organization, $this->customer(['country' => 'CH']), null, 'services', new PlaceOfSupply(PlaceOfSupplyKind::Catalog));

        $this->assertSame((string) config('taxation.notes.catalog_third_country'), $catalog['note']);
        $this->assertStringContainsString('§3a Abs. 4', (string) $catalog['note']);
        $this->assertSame((string) config('taxation.notes.property_abroad'), $property['note']);
        $this->assertStringContainsString('§3a Abs. 3 Nr. 1', (string) $property['note']);
        $this->assertSame((string) config('taxation.notes.export'), $company['note'], 'Unternehmen im Drittland behalten den Hinweis nach § 3a Abs. 2.');
    }

    /** Gegenprobe Bestand: Ohne Merkmal und ohne Leistungsort-Art entsteht derselbe Fall wie vor MVP-1688. */
    public function test_without_maintenance_every_tax_case_stays_as_before(): void {
        $resolver = app(TaxResolver::class);
        $before = [
            'DE' => [['country' => 'DE'], 'services', 'S', '19.00'],
            'AT mit USt-IdNr.' => [['country' => 'AT', 'vat_id' => 'ATU13585627'], 'services', 'AE', '0.00'],
            'AT ohne USt-IdNr.' => [['country' => 'AT'], 'services', 'S', '19.00'],
            'CH Leistung' => [['country' => 'CH'], 'services', 'O', '0.00'],
            'CH Ware' => [['country' => 'CH'], 'goods', 'G', '0.00'],
            'US Leistung' => [['country' => 'US'], 'services', 'O', '0.00'],
        ];
        foreach ($before as $label => [$attributes, $category, $expected, $rate]) {
            $customer = $this->customer($attributes);
            $this->assertFalse((bool) $customer->refresh()->is_consumer, 'Neue Kunden gelten als Unternehmen.');

            $tax = $resolver->resolve($this->organization, $customer, null, $category);

            $this->assertSame([$expected, $rate], [$tax['category'], $tax['rate']], $label);
            $this->assertSame($tax, $resolver->resolve($this->organization, $customer, null, $category, new PlaceOfSupply(PlaceOfSupplyKind::General)), $label);
        }

        // Rechnungslauf aus Zeiten ohne Regel: CH-Unternehmen bleibt nicht steuerbar.
        $customer = $this->customer(['country' => 'CH']);
        $invoice = $this->issueFromTimes($customer, $this->project($customer), null);
        $this->assertSame('O', data_get($invoice->tax_context, 'category'));
    }

    /** Die Vorgabe der Organisation gilt, wo weder Regel noch Artikel eine Art nennen. */
    public function test_organization_default_applies_without_explicit_place(): void {
        $consumer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $this->assertSame('S', app(TaxResolver::class)->resolve($this->organization, $consumer)['category']);

        app(SettingsRegistry::class)->set('invoicing.place_of_supply_kind', 'catalog', SettingScope::Organization, $this->organization->refresh(), $this->admin->id);

        $this->assertSame('O', app(TaxResolver::class)->resolve($this->organization->refresh(), $consumer)['category']);
    }

    /**
     * Rechnungslauf: Katalogleistung (Abrechnungsregel) an einen Verbraucher in der
     * Schweiz ist nicht steuerbar — E-Rechnung, Hinweis und Buchhaltung aus derselben
     * eingefrorenen Kategorie, KoSIT nimmt UBL und CII an.
     */
    public function test_catalog_service_to_a_consumer_in_a_third_country_is_not_taxable(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $project = $this->project($customer, PlaceOfSupplyKind::Catalog);

        $invoice = $this->issueFromTimes($customer, $project, null);

        $this->assertSame('O', data_get($invoice->tax_context, 'category'));
        $this->assertTrue($invoice->tax_rate?->isZero());
        $this->assertStringContainsString((string) config('taxation.notes.catalog_third_country'), (string) $invoice->notes);
        $this->assertSame('1', $invoice->recipient()['is_consumer']);
        $generator = app(XRechnungGenerator::class);
        $this->assertSame([], $generator->preflight($invoice)['errors']);
        foreach ([XRechnungSyntax::Ubl, XRechnungSyntax::Cii] as $syntax) {
            $xml = $generator->generate($invoice, $syntax);
            $this->assertStringContainsString('VATEX-EU-O', $xml, $syntax->value);
            $this->assertNull((new ERechnungParser)->parse($xml)->getBuyer()->getVatId(), $syntax->value);
            $this->assertKositAccepted($xml);
        }
        $this->assertSame(['category' => 'O', 'domestic' => false], app(SalesInvoiceAdapter::class)->zeroRatedCategory($this->organization, $invoice));

        // Gegenprobe: dieselbe Leistung als „allgemein“ trägt deutsche Steuer.
        $general = $this->issueFromTimes($customer, $this->project($customer, PlaceOfSupplyKind::General), null);
        $this->assertSame('S', data_get($general->tax_context, 'category'));
        $this->assertSame('19.00', $general->tax_rate?->getNumericValue());
    }

    /**
     * Grundstück im Inland für ein Unternehmen in Österreich: deutsche Steuer statt
     * Reverse Charge, ohne USt-IdNr.-Pflicht in der E-Rechnung; KoSIT nimmt an.
     */
    public function test_property_in_germany_for_an_eu_business_carries_german_vat(): void {
        foreach (['ATU13585627', null] as $vatId) {
            $customer = $this->customer(['country' => 'AT', 'vat_id' => $vatId, 'address_zip' => '4020', 'address_city' => 'Linz']);
            $project = $this->project($customer, PlaceOfSupplyKind::Property);

            $invoice = $this->issueFromTimes($customer, $project, null);

            $this->assertSame('S', data_get($invoice->tax_context, 'category'));
            $this->assertFalse((bool) $invoice->is_reverse_charge);
            $this->assertSame('19.00', $invoice->tax_rate?->getNumericValue());
            $generator = app(XRechnungGenerator::class);
            $this->assertSame([], $generator->preflight($invoice)['errors'], (string) $vatId);
            $this->assertKositAccepted($generator->generate($invoice));
        }
    }

    /** Grundstück am Einsatzort im Ausland: nicht steuerbar, auch für den Kunden im Inland. */
    public function test_property_abroad_follows_the_site_of_the_order(): void {
        $customer = $this->customer(['country' => 'DE']);
        $project = $this->project($customer, PlaceOfSupplyKind::Property);

        $invoice = $this->issueFromTimes($customer, $project, 'AT');

        $this->assertSame('O', data_get($invoice->tax_context, 'category'));
        $this->assertStringContainsString((string) config('taxation.notes.property_abroad'), (string) $invoice->notes);
        $this->assertSame([], app(XRechnungGenerator::class)->preflight($invoice)['errors']);
        $this->assertKositAccepted(app(XRechnungGenerator::class)->generate($invoice));
    }

    /**
     * Mischt eine Rechnung danach O mit anderen Kategorien, hält der Entwurf an:
     * kein Ausstellen, die Prüfung vor dem Versand weist nach BR-O-11 ab.
     */
    public function test_mixed_places_of_supply_block_issue_and_preflight(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $this->articleLine($draft, $this->article(PlaceOfSupplyKind::Catalog), 1);
        $this->articleLine($draft, $this->article(PlaceOfSupplyKind::General), 2);
        $draft->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Mixed, app(InvoiceIssueService::class)->alignTaxCase($draft));
        $cases = app(TaxResolver::class)->casesForInvoice($draft);
        $this->assertSame(['O', 'S'], array_column($cases, 'category'));
        $this->get(route('invoices.show', $draft))->assertOk()->assertSee(__('invoicing.tax_case.place_of_supply_mixed'));

        // Wie im Versandweg: der Entwurf als ausgestellt im Speicher.
        $probe = clone $draft;
        $probe->status = InvoiceStatus::Issued;
        $probe->issued_on = now();
        $this->assertContains((string) __('invoicing.einvoice.error.not_subject_mixed'), app(XRechnungGenerator::class)->preflight($probe)['errors']);

        try {
            app(InvoiceIssueService::class)->issue($draft);
            $this->fail('Eine Rechnung mit verschiedenen Steuerfällen darf nicht ausgestellt werden.');
        } catch (InvoiceIssueException $e) {
            $this->assertSame(InvoiceIssueException::REASON_PLACE_OF_SUPPLY_MIXED, $e->reason);
        }
        $this->assertSame(InvoiceStatus::Draft, $draft->refresh()->status);

        // Geteilt: je Steuerfall eine Rechnung; die nächste Bearbeitung zieht den Fall nach, dann lässt sie sich stellen.
        $draft->items()->where('position', 2)->delete();
        $draft->refresh()->load(['items', 'customer', 'organization']);
        $this->assertSame(TaxCaseAlignment::Aligned, app(InvoiceIssueService::class)->alignTaxCase($draft));
        $draft->recalculate();
        $draft->save();
        $issued = app(InvoiceIssueService::class)->issue($draft->refresh());
        $this->assertSame('O', data_get($issued->refresh()->tax_context, 'category'));
    }

    /** Ohne O bleibt die Mischung ein eigener Hinweis: Grundstück im Inland neben Reverse Charge. */
    public function test_mixed_cases_without_out_of_scope_name_the_split(): void {
        $customer = $this->customer(['country' => 'AT', 'vat_id' => 'ATU13585627']);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $this->articleLine($draft, $this->article(PlaceOfSupplyKind::Property), 1);
        $this->articleLine($draft, $this->article(PlaceOfSupplyKind::General), 2);
        $draft->load(['items', 'customer', 'organization']);

        $probe = clone $draft;
        $probe->status = InvoiceStatus::Issued;
        $errors = app(XRechnungGenerator::class)->preflight($probe)['errors'];

        $this->assertContains((string) __('invoicing.tax_case.place_of_supply_mixed'), $errors);
        $this->assertNotContains((string) __('invoicing.einvoice.error.not_subject_mixed'), $errors);
    }

    /** Ein Leistungsartikel am Entwurf zieht den Steuerfall nach; eine Ware zählt nicht. */
    public function test_service_article_aligns_the_draft_and_goods_do_not_count(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $draft = app(InvoiceGenerator::class)->emptyDraft($customer);
        $this->assertSame('19.00', $draft->tax_rate?->getNumericValue());

        $goods = Article::factory()->create(['organization_id' => $this->organization->id, 'type' => ArticleType::Merchandise->value, 'place_of_supply_kind' => PlaceOfSupplyKind::General->value]);
        $this->articleLine($draft, $goods, 1);
        $this->articleLine($draft, $this->article(PlaceOfSupplyKind::Catalog), 2);
        $draft->load(['items', 'customer', 'organization']);

        $this->assertSame(TaxCaseAlignment::Aligned, app(InvoiceIssueService::class)->alignTaxCase($draft));
        $this->assertTrue($draft->tax_rate?->isZero());
        $this->assertStringContainsString((string) config('taxation.notes.catalog_third_country'), (string) $draft->notes);
    }

    /** Storno und Korrekturrechnung tragen Fall und Merkmal des Originals, auch wenn sich der Kunde danach ändert. */
    public function test_cancellation_keeps_the_tax_case_and_consumer_flag_of_the_original(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $original = $this->issueFromTimes($customer, $this->project($customer, PlaceOfSupplyKind::Catalog), null);
        $this->assertSame('O', data_get($original->tax_context, 'category'));

        $customer->update(['is_consumer' => false, 'country' => 'DE']);
        $cancellation = app(InvoiceGenerator::class)->cancellationFor($original->refresh(), 'Doppelt berechnet', $this->admin->id);
        $cancellation = app(InvoiceIssueService::class)->issue($cancellation->refresh())->refresh();

        $this->assertSame('O', data_get($cancellation->tax_context, 'category'));
        $this->assertSame('1', $cancellation->recipient()['is_consumer']);
        $this->assertSame('0', \App\Services\Invoicing\InvoicePartySnapshot::buyer($customer->refresh())['is_consumer']);
    }

    /** Vor MVP-1688 ausgestellt: Der Empfänger galt als Unternehmen, ein heutiges Merkmal deutet den Beleg nicht um. */
    public function test_snapshot_without_the_flag_reads_as_business(): void {
        $customer = $this->customer(['country' => 'DE', 'is_consumer' => true]);
        $invoice = Invoice::factory()->create(['organization_id' => $this->organization->id, 'customer_id' => $customer->id]);
        $invoice->forceFill(['party_snapshot' => ['buyer' => ['name' => 'Altkunde', 'country' => 'DE']]])->saveQuietly();

        $this->assertSame('0', $invoice->refresh()->recipient()['is_consumer']);
    }

    /** Angebot → Rechnung: Der Leistungsort der angenommenen Leistungsartikel bestimmt den Kopf. */
    public function test_quote_conversion_takes_the_place_of_supply_of_its_articles(): void {
        $customer = $this->customer(['country' => 'CH', 'is_consumer' => true]);
        $article = $this->article(PlaceOfSupplyKind::Catalog);
        $quote = \App\Models\Sales\Quote::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => \App\Enums\Sales\QuoteStatus::Accepted,
            'decision_snapshot' => ['items' => [[
                'accepted' => true, 'article_id' => $article->id, 'description' => 'Datenschutzberatung',
                'quantity' => '2', 'unit_price' => '120.00', 'tax_rate' => '19.00', 'unit' => 'Std.',
            ]]],
        ]);

        $invoice = app(\App\Services\Invoicing\QuoteService::class)->convertToInvoice($quote, $this->admin);

        $this->assertTrue($invoice->tax_rate?->isZero());
        $this->assertNull($invoice->items()->first()?->tax_rate, 'Bei 0 % gelten keine Positionssätze.');
        $this->assertStringContainsString((string) config('taxation.notes.catalog_third_country'), (string) $invoice->notes);
    }

    /** @param array<string, mixed> $attributes */
    private function customer(array $attributes): Customer {
        return Customer::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'name' => 'Kunde ' . fake()->unique()->numberBetween(1, 99999),
            'currency' => 'EUR',
            'email' => 'buchhaltung@kunde.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '8001',
            'address_city' => 'Kundenstadt',
            'buyer_reference' => '991-12345-73',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    private function project(Customer $customer, ?PlaceOfSupplyKind $kind = null): Project {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Projekt ' . fake()->unique()->numberBetween(1, 99999),
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        if ($kind !== null) {
            $project->billingRules()->create([
                'organization_id' => $this->organization->id,
                'applies_to_kind' => null,
                'item_type' => 'service',
                'place_of_supply_kind' => $kind->value,
            ]);
        }

        return $project;
    }

    /** Rechnungslauf über die Zeiten eines Auftrags (Einsatzort im Land $siteCountry), ausgestellt. */
    private function issueFromTimes(Customer $customer, Project $project, ?string $siteCountry): Invoice {
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'title' => 'Einsatz',
            'address_country' => $siteCountry,
        ]);
        $from = CarbonImmutable::parse('2030-04-02 08:00');
        TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'diary_entry_id' => $order->id,
            'user_id' => $this->admin->id,
            'date' => $from->toDateString(),
            'started_at' => $from,
            'ended_at' => $from->addHours(2),
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Leistung vor Ort',
        ]);

        $draft = app(InvoiceGenerator::class)->fromTimeEntries($customer, $project, ['from' => '2030-04-01', 'to' => '2030-04-30']);

        return app(InvoiceIssueService::class)->issue($draft->refresh())->refresh();
    }

    private function article(PlaceOfSupplyKind $kind): Article {
        return Article::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ArticleType::Service->value,
            'base_unit' => 'Std.',
            'place_of_supply_kind' => $kind->value,
        ]);
    }

    private function articleLine(Invoice $invoice, Article $article, int $position): void {
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'description' => $article->name,
            'quantity' => '2',
            'unit' => 'Std.',
            'unit_price' => '80.00',
            'service_date' => '2030-04-15',
            'position' => $position,
        ]);
    }

    private function assertKositAccepted(string $xml): void {
        $kosit = new KositValidator;
        if (! $kosit->isAvailable()) {
            $this->markTestSkipped('KoSIT-Validator (Java + JAR) ist nicht verfügbar.');
        }
        $result = $kosit->validate($xml);
        $this->assertSame([], array_map('strval', $result->getErrors()));
        $this->assertTrue($result->isAccepted());
    }
}
