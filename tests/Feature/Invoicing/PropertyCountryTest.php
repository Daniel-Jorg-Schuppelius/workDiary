<?php
/*
 * Created on   : Fri Nov 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PropertyCountryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Invoicing;

use App\Enums\Article\ArticleType;
use App\Enums\Finance\BillingMode;
use App\Enums\Invoicing\{InvoiceStatus, PlaceOfSupplyKind};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Article\Article;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\Time\TimeEntry;
use App\Services\Invoicing\EInvoice\XRechnungGenerator;
use App\Services\Invoicing\{InvoiceGenerator, InvoiceIssueService, PlaceOfSupplyResolver, TaxResolver};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1706 (Entscheidung des Inhabers 2026-11-13): optionales Land „Ort des
 * Grundstücks“ an der Rechnung, nur bei Grundstücksleistungen sichtbar; leer
 * gilt der Einsatzort des Auftrags bzw. das Inland. Es geht dem Einsatzort
 * vor, wird beim Ausstellen festgeschrieben, Storno und Korrektur übernehmen
 * es; E-Rechnung, PDF und Buchhaltung folgen der eingefrorenen Kategorie.
 */
final class PropertyCountryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update(['settings' => ['einvoice' => [
            'seller_name' => 'Fliesenbau Beispiel GmbH',
            'street' => 'Musterstraße 1',
            'zip' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'vat_id' => 'DE136695976',
            'tax_number' => '201/987/65432',
            'contact_name' => 'Max Muster',
            'contact_email' => 'rechnung@fliesenbau.example',
            'contact_phone' => '+49 30 123456',
            'iban' => 'DE89370400440532013000',
            'bic' => 'COBADEFFXXX',
            'account_holder' => 'Fliesenbau Beispiel GmbH',
        ]]]);
        $this->admin = $this->orgAdmin(['hourly_rate' => null]);
        $this->actingAs($this->admin);
        $this->customer = Customer::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Hausverwaltung Beispiel',
            'company' => 'Hausverwaltung Beispiel GmbH',
            'currency' => 'EUR',
            'email' => 'buchhaltung@hausverwaltung.example',
            'address_street' => 'Kundenweg 7',
            'address_zip' => '10115',
            'address_city' => 'Berlin',
            'country' => 'DE',
            'buyer_reference' => '991-12345-73',
            'billing_mode' => BillingMode::Workdiary,
            'created_by' => $this->admin->id,
        ]);
    }

    /** Rechnung ohne Auftrag: Grundstück in Österreich über den Dialog → nicht steuerbar, festgeschrieben, Storno übernimmt. */
    public function test_property_abroad_without_order_is_not_taxable_and_frozen(): void {
        $draft = $this->draftWithLine(PlaceOfSupplyKind::Property);
        $this->assertSame('19.00', $draft->tax_rate?->getNumericValue(), 'Ohne Angabe liegt das Grundstück im Inland.');

        $this->patch(route('invoices.conditions.update', $draft), ['property_country' => ' at '])->assertRedirect()->assertSessionHasNoErrors();

        $draft->refresh();
        $this->assertSame('AT', $draft->property_country);
        $this->assertTrue($draft->tax_rate?->isZero());
        $this->assertStringContainsString((string) config('taxation.notes.property_abroad'), (string) $draft->notes);

        $issued = app(InvoiceIssueService::class)->issue($draft)->refresh();
        $this->assertSame('O', data_get($issued->tax_context, 'category'));
        $this->assertSame('AT', $issued->property_country);
        $this->assertSame([], app(XRechnungGenerator::class)->preflight($issued)['errors']);

        // Festgeschrieben: eine ausgestellte Rechnung ändert das Land nicht mehr.
        try {
            $issued->update(['property_country' => 'DE']);
            $this->fail('Der Ort des Grundstücks einer ausgestellten Rechnung ist unveränderlich.');
        } catch (\RuntimeException) {
            // erwartet
        }
        $this->assertSame('AT', $issued->refresh()->property_country);

        $cancellation = app(InvoiceGenerator::class)->cancellationFor($issued, 'Doppelt berechnet', $this->admin->id);
        $this->assertSame('AT', $cancellation->property_country, 'Storno übernimmt den Ort des Grundstücks.');
        $cancellation = app(InvoiceIssueService::class)->issue($cancellation->refresh())->refresh();
        $this->assertSame('O', data_get($cancellation->tax_context, 'category'));
    }

    public function test_corrective_invoice_takes_over_the_property_country(): void {
        $draft = $this->draftWithLine(PlaceOfSupplyKind::Property);
        $this->patch(route('invoices.conditions.update', $draft), ['property_country' => 'AT'])->assertSessionHasNoErrors();
        $issued = app(InvoiceIssueService::class)->issue($draft->refresh())->refresh();
        $issued->update(['status' => InvoiceStatus::Paid, 'paid_on' => '2030-05-02']);

        $credit = app(InvoiceGenerator::class)->creditNoteFor($issued->refresh(), $this->admin->id);

        $this->assertSame('AT', $credit->property_country);
        $this->assertSame('O', $credit->items()->first()?->tax_category, 'Die Positionen tragen die Kategorie des Originals.');
    }

    /** Leer: Einsatzort des Auftrags; gesetzt: Vorrang vor dem Einsatzort. */
    public function test_property_country_takes_precedence_over_the_site_of_the_order(): void {
        $draft = $this->draftFromOrder('FR');
        $this->assertSame(['O'], array_column(app(TaxResolver::class)->casesForInvoice($draft), 'category'), 'Leer gilt der Einsatzort (Frankreich).');

        $draft->update(['property_country' => 'DE']);
        $draft->refresh();

        $this->assertSame(['S'], array_column(app(TaxResolver::class)->casesForInvoice($draft), 'category'), 'Gesetzt geht das Land dem Einsatzort vor.');
        $this->assertSame('DE', app(PlaceOfSupplyResolver::class)->forInvoice($draft)[0]->propertyCountry);
    }

    /** Gegenprobe: leer und ohne Auftrag gilt das Inland; für eine allgemeine Leistung zählt das Land nicht. */
    public function test_empty_means_domestic_and_general_services_ignore_the_country(): void {
        $property = $this->draftWithLine(PlaceOfSupplyKind::Property);
        $this->assertSame(['S'], array_column(app(TaxResolver::class)->casesForInvoice($property), 'category'));

        $general = $this->draftWithLine(PlaceOfSupplyKind::General);
        $general->update(['property_country' => 'AT']);
        $general->refresh();

        $this->assertSame(['S'], array_column(app(TaxResolver::class)->casesForInvoice($general), 'category'));
        $this->assertNull(app(PlaceOfSupplyResolver::class)->forInvoice($general)[0]->propertyCountry);
    }

    public function test_dialog_shows_the_field_only_with_a_property_service(): void {
        $property = $this->draftWithLine(PlaceOfSupplyKind::Property);
        $general = $this->draftWithLine(PlaceOfSupplyKind::General);

        $this->get(route('invoices.conditions.form', $property))->assertOk()
            ->assertSee('name="property_country"', false)
            ->assertSee(__('invoicing.property_country.label'));
        $this->get(route('invoices.conditions.form', $general))->assertOk()
            ->assertDontSee('name="property_country"', false);

        // Ohne das Feld im Formular bleibt ein gesetztes Land stehen.
        $property->update(['property_country' => 'AT']);
        $this->patch(route('invoices.conditions.update', $property), ['skonto_percent' => '2', 'skonto_days' => '10'])->assertSessionHasNoErrors();
        $this->assertSame('AT', $property->refresh()->property_country);
    }

    public function test_unknown_country_is_rejected_and_empty_clears(): void {
        $draft = $this->draftWithLine(PlaceOfSupplyKind::Property);

        $this->patch(route('invoices.conditions.update', $draft), ['property_country' => 'XX'])->assertSessionHasErrors('property_country');
        $this->assertNull($draft->refresh()->property_country);

        $draft->update(['property_country' => 'AT']);
        $this->patch(route('invoices.conditions.update', $draft), ['property_country' => ''])->assertSessionHasNoErrors();
        $this->assertNull($draft->refresh()->property_country);
    }

    public function test_show_page_and_api_name_the_country(): void {
        $draft = $this->draftWithLine(PlaceOfSupplyKind::Property);
        $draft->update(['property_country' => 'AT']);

        $this->get(route('invoices.show', $draft))->assertOk()
            ->assertSee(__('invoicing.property_country.shown'))
            ->assertSee('(AT)');

        Sanctum::actingAs($this->admin, ['invoices:read']);
        $this->getJson(route('api.invoices.show', $draft))->assertOk()->assertJsonPath('data.property_country', 'AT');
    }

    private function draftWithLine(PlaceOfSupplyKind $kind): Invoice {
        $draft = app(InvoiceGenerator::class)->emptyDraft($this->customer);
        $article = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'type' => ArticleType::Service->value,
            'base_unit' => 'Std.',
            'place_of_supply_kind' => $kind->value,
        ]);
        $draft->items()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'description' => 'Fliesenarbeiten',
            'quantity' => '4',
            'unit' => 'Std.',
            'unit_price' => '65.00',
            'service_date' => '2030-04-15',
            'position' => 1,
        ]);
        $draft->load(['items', 'customer', 'organization']);
        app(InvoiceIssueService::class)->alignTaxCase($draft);
        $draft->recalculate();
        $draft->save();

        return $draft->refresh();
    }

    /** Rechnungslauf über einen Auftrag mit Einsatzort im Land $siteCountry, Grundstücksleistung über die Abrechnungsregel. */
    private function draftFromOrder(string $siteCountry): Invoice {
        $project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'status' => ProjectStatus::Active->value,
            'is_default' => false,
        ]);
        $project->billingRules()->create([
            'organization_id' => $this->organization->id,
            'applies_to_kind' => null,
            'item_type' => 'service',
            'place_of_supply_kind' => PlaceOfSupplyKind::Property->value,
        ]);
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->admin->id,
            'project_id' => $project->id,
            'customer_id' => $this->customer->id,
            'title' => 'Bad sanieren',
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
            'description' => 'Fliesen legen',
        ]);

        $draft = app(InvoiceGenerator::class)->fromTimeEntries($this->customer, $project, ['from' => '2030-04-01', 'to' => '2030-04-30']);
        $this->assertSame(InvoiceStatus::Draft, $draft->status);

        return $draft->refresh();
    }
}
