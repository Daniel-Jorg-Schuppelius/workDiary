<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferPositionBuildTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Enums\Finance\{TransferChannel, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, LexofficeArticle, Project, ProjectBillingRule, TimeEntry, User};
use App\Services\Billing\OrganizationDefaultRateResolver;
use App\Services\Finance\{BillingPositionBuilder, BillingTransferService};
use App\Services\Invoicing\{BlockPrice, ServiceDefaultResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Positionsaufbau der Faktura-Übergabe (MVP-485–488): Preisfindung,
 * Standardleistung, Text mit Leistungsdatum und das Einfrieren beim
 * Bestätigen.
 */
class TransferPositionBuildTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => null,
        ]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'hourly_rate' => null,
            'currency' => 'EUR',
        ]);
        $this->project = Project::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Wartung IT',
            'status' => ProjectStatus::Active->value,
            'hourly_rate' => null,
            'is_default' => false,
        ]);
    }

    private function entry(array $attributes = []): TimeEntry {
        return TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'started_at' => '2030-04-01 09:00:00',
            'ended_at' => '2030-04-01 11:00:00',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'description' => 'Server geprüft',
        ], $attributes));
    }

    private function draft(): \App\Models\Finance\BillingTransfer {
        return app(BillingTransferService::class)->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::File,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
    }

    private function article(array $attributes = []): LexofficeArticle {
        return LexofficeArticle::create(array_merge([
            'organization_id' => $this->organization->id,
            'external_id' => 'art-1',
            'name' => 'IT-Dienstleistung',
            'description' => 'Betreuung der IT-Systeme nach Aufwand.',
            'type' => 'service',
            'unit_name' => 'Stunde',
            'net_unit_price' => '120.0000',
            'currency' => 'EUR',
            'vat_rate' => '19.00',
        ], $attributes));
    }

    private function setOrgDefaultRate(float $rate): void {
        $this->organization->update(['settings' => array_replace_recursive(
            (array) $this->organization->settings,
            ['invoicing' => ['default_hourly_rate' => $rate]],
        )]);
        app(OrganizationDefaultRateResolver::class)->flush();
    }

    private function setOrgDefaultService(string $externalId): void {
        $this->organization->update(['settings' => array_replace_recursive(
            (array) $this->organization->settings,
            ['invoicing' => ['default_service_article' => $externalId]],
        )]);
        app(ServiceDefaultResolver::class)->flush();
    }

    public function test_ohne_jeden_satz_bleibt_die_position_ohne_preis(): void {
        $this->entry();

        $positions = app(BillingPositionBuilder::class)->build($this->draft());

        $this->assertCount(1, $positions);
        $this->assertSame(0.0, $positions->first()->unitPriceFloat());
        $this->assertSame(BlockPrice::SOURCE_NONE, $positions->first()->price_source);
        $this->assertTrue($positions->first()->isUnpriced());
    }

    public function test_org_standarderloes_greift_auch_ohne_nachbewertung(): void {
        $this->entry();
        // Satz erst NACH der Zeiterfassung gepflegt: der rate-Snapshot ist 0.
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertSame(90.0, $position->unitPriceFloat());
        $this->assertSame(BlockPrice::SOURCE_ORG_DEFAULT, $position->price_source);
        $this->assertSame(180.0, $position->amountFloat());
    }

    public function test_standardleistung_liefert_bezeichnung_einheit_text_und_preis(): void {
        $this->entry();
        $this->article();
        $this->setOrgDefaultService('art-1');

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertSame('IT-Dienstleistung', $position->name);
        $this->assertSame('Stunde', $position->unit_name);
        $this->assertSame('art-1', $position->article_id);
        $this->assertSame(120.0, $position->unitPriceFloat());
        $this->assertSame(BlockPrice::SOURCE_SERVICE, $position->price_source);
        $this->assertStringContainsString('Betreuung der IT-Systeme', (string) $position->description);
        $this->assertStringContainsString('Server geprüft', (string) $position->description);
        $this->assertStringContainsString('01.04.2030', (string) $position->description);
    }

    public function test_gepflegter_satz_schlaegt_den_leistungspreis(): void {
        $this->customer->update(['hourly_rate' => '95.00']);
        $this->entry();
        $this->article();
        $this->setOrgDefaultService('art-1');

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertSame(95.0, $position->unitPriceFloat());
        $this->assertSame(BlockPrice::SOURCE_SNAPSHOT, $position->price_source);
        // Bezeichnung/Artikel kommen weiter aus der Leistung.
        $this->assertSame('art-1', $position->article_id);
    }

    public function test_projektregel_schlaegt_die_org_standardleistung(): void {
        $this->entry();
        $this->article();
        $this->article(['external_id' => 'art-2', 'name' => 'Projektpauschale', 'unit_name' => 'Stunde', 'net_unit_price' => '150.0000']);
        $this->setOrgDefaultService('art-1');

        ProjectBillingRule::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'lexoffice_article_id' => 'art-2',
            'item_type' => 'service',
            'priority' => 0,
        ]);
        app(ServiceDefaultResolver::class)->flush();

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertSame('art-2', $position->article_id);
        $this->assertSame(150.0, $position->unitPriceFloat());
    }

    public function test_regelpreis_schlaegt_den_gepflegten_satz(): void {
        // Ein an der Projektregel gepflegter Preis ist eine bewusste Ansage
        // für dieses Projekt — er gewinnt auch gegen den Kundensatz.
        $this->customer->update(['hourly_rate' => '95.00']);
        $this->entry();
        $this->article();

        ProjectBillingRule::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'plugin_id' => 'lexoffice',
            'applies_to_kind' => null,
            'lexoffice_article_id' => 'art-1',
            'item_type' => 'service',
            'net_unit_price' => 123.45,
            'priority' => 0,
        ]);
        app(ServiceDefaultResolver::class)->flush();

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertSame(123.45, $position->unitPriceFloat());
        $this->assertSame(BlockPrice::SOURCE_SERVICE, $position->price_source);
    }

    public function test_bestaetigen_friert_die_positionen_ein(): void {
        $this->entry();
        $this->setOrgDefaultRate(90.0);

        $transfer = $this->draft();
        $this->assertSame(0, $transfer->positions()->count());

        app(BillingTransferService::class)->confirm($transfer, $this->accountant);

        $frozen = $transfer->fresh()->positions;
        $this->assertCount(1, $frozen);
        $this->assertSame(90.0, (float) $frozen->first()->unit_price);

        // Spätere Satzänderung wirkt nicht mehr auf die eingefrorene Position.
        $this->setOrgDefaultRate(120.0);
        $this->assertSame(90.0, (float) $transfer->fresh()->positions->first()->unit_price);
    }

    public function test_bestaetigen_setzt_die_kopfzahlen_auf_die_positionen(): void {
        $this->entry();
        // Satz erst nach der Erfassung gepflegt ⇒ Quellsumme 0,00 €.
        $this->setOrgDefaultRate(90.0);

        $transfer = $this->draft();
        $this->assertSame(0.0, (float) $transfer->total_amount, 'Quellsumme bleibt 0, solange nichts nachbewertet wurde.');

        app(BillingTransferService::class)->confirm($transfer, $this->accountant);

        $transfer->refresh();
        $this->assertSame(180.0, (float) $transfer->total_amount);
        $this->assertSame(2.0, (float) $transfer->total_quantity);
        $this->assertSame(1, (int) $transfer->position_count);
    }

    public function test_leistungszeitraum_bei_mehreren_tagen(): void {
        $this->project->update(['billing_grouping_gap_minutes' => 10000]);
        $this->entry();
        $this->entry(['date' => '2030-04-02', 'started_at' => '2030-04-02 09:00:00', 'ended_at' => '2030-04-02 10:00:00', 'minutes' => 60, 'description' => 'Update eingespielt']);
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertStringContainsString('01.04.2030', (string) $position->description);
        $this->assertStringContainsString('02.04.2030', (string) $position->description);
        $this->assertStringContainsString('Server geprüft', (string) $position->description);
        $this->assertStringContainsString('Update eingespielt', (string) $position->description);
    }

    public function test_woerterbuch_korrigiert_vorschau_und_eingefrorene_positionen(): void {
        \App\Models\TextCorrection::factory()->create([
            'organization_id' => $this->organization->id,
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ]);
        $entry = $this->entry(['description' => 'Server geprüfft']);
        $this->setOrgDefaultRate(90.0);

        // Vorschau (build) korrigiert …
        $transfer = $this->draft();
        $preview = app(BillingPositionBuilder::class)->build($transfer)->first();
        $this->assertStringContainsString('Server geprüft', (string) $preview->description);
        $this->assertStringNotContainsString('geprüfft', (string) $preview->description);

        // … und die eingefrorene Fassung identisch (freeze → build).
        app(BillingTransferService::class)->confirm($transfer, $this->accountant);
        $frozen = $transfer->fresh()->positions->first();
        $this->assertStringContainsString('Server geprüft', (string) $frozen->description);

        // Quelldaten bleiben unangetastet.
        $this->assertSame('Server geprüfft', $entry->fresh()->description);
    }

    public function test_inaktiver_woerterbuch_eintrag_wirkt_nicht(): void {
        \App\Models\TextCorrection::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
            'wrong' => 'geprüfft',
            'correct' => 'geprüft',
        ]);
        $this->entry(['description' => 'Server geprüfft']);
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertStringContainsString('Server geprüfft', (string) $position->description);
    }

    // ── Endkunde (Fremdkunde des Projekts) in Bezeichnung und Text ──────────

    private function foreignCustomer(?string $company, string $name): \App\Models\ForeignCustomer {
        return \App\Models\ForeignCustomer::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => $name,
            'company' => $company,
        ]);
    }

    public function test_endkunde_erscheint_in_bezeichnung_und_text(): void {
        $this->project->update(['foreign_customer_id' => $this->foreignCustomer(null, 'Kiesewetter')->id]);
        $this->entry();
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertStringStartsWith(__('Endkunde :name', ['name' => 'Kiesewetter']) . ' · ', (string) $position->name);
        $this->assertStringContainsString(__('Endkunde :name', ['name' => 'Kiesewetter']), (string) $position->description);
        $this->assertStringContainsString('Server geprüft', (string) $position->description);
    }

    public function test_endkunde_firma_hat_vorrang_vor_dem_namen(): void {
        // Gleiche Auflösung wie in der Direktrechnung (InvoiceGenerator::bookingLine).
        $this->project->update(['foreign_customer_id' => $this->foreignCustomer('Sysdec', 'Gunnar Geithner')->id]);
        $this->entry();
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertStringStartsWith(__('Endkunde :name', ['name' => 'Sysdec']) . ' · ', (string) $position->name);
        $this->assertStringNotContainsString('Gunnar Geithner', (string) $position->name);
    }

    public function test_ohne_endkunde_kein_endkunde_praefix(): void {
        $this->entry();
        $this->setOrgDefaultRate(90.0);

        $position = app(BillingPositionBuilder::class)->build($this->draft())->first();

        $this->assertStringNotContainsString(__('Endkunde'), (string) $position->name);
        $this->assertStringNotContainsString(__('Endkunde'), (string) $position->description);
    }
}
