<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImporterReviewTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{SubscriptionProvider, SubscriptionStatus};
use App\Models\Customer;
use App\Models\Reselling\{ResaleImport, ResalePriceEntry, ResaleSubscription};
use App\Services\Reselling\Register\MarketplaceImporter;
use App\Support\XlsxExport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\{Log, Storage};
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Review 2026-09-10, Import-Seite (Feature 152): Abtretungen bei der Ablösung
 * (B10), Einkaufspreis und Status beim Re-Import, Preiskatalog-Gültigkeiten
 * (B11), Domain-Anbieter im Datei-Import (B7), Zeilenbefunde bis zur Konsole (A1).
 */
class ImporterReviewTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake(ResaleImport::DISK);
        $this->travelTo('2026-09-08');
    }

    protected function tearDown(): void {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function csv(string $content): string {
        $path = sys_get_temp_dir() . '/generic-' . uniqid() . '.csv';
        file_put_contents($path, "\xEF\xBB\xBF" . $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @param  list<array{0: string, 1: float, 2: float}>  $products  Produkt, Preis je Monat, UVP je Monat
     */
    private function priceList(string $validFrom, array $products): string {
        $path = sys_get_temp_dir() . '/qh-prices-' . uniqid() . '.xlsx';
        $rows = [];
        foreach ($products as [$product, $price, $uvp]) {
            $rows[] = [$product, 12, 'jährlich', null, $price, $uvp, round($price * 12, 2), round($uvp * 12, 2), 'K', 'O'];
        }
        file_put_contents($path, XlsxExport::toStringMultiSheet([
            ['title' => 'Deckblatt', 'headers' => ['Preisliste für Reseller', ''], 'rows' => [['Reseller', '95229'], ['Gültigkeit ab', $validFrom]]],
            ['title' => 'Preisdaten', 'headers' => ['Produkttarif', 'Vertragslaufzeit in Monaten', 'Zahlungsintervall', 'Gültig ab', 'Preis pro Monat', 'Hersteller-UVP pro Monat', 'Preis pro Zahlungsintervall', 'Hersteller-UVP pro Zahlungsintervall', 'Hersteller-Key', 'Offer-Key'], 'rows' => $rows],
        ]));
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @return list<ResaleImport>
     */
    private function importGeneric(string $csv, ?CarbonImmutable $reference = null): array {
        return app(MarketplaceImporter::class)->import($this->organization, null, [ResaleImport::KIND_GENERIC => ['name' => 'liste.csv', 'path' => $this->csv($csv)]], $reference, SubscriptionProvider::Manual);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function subscription(array $attributes): ResaleSubscription {
        return ResaleSubscription::query()->create(array_merge([
            'organization_id' => $this->organization->id, 'kind' => 'license', 'label' => 'Exchange Online Plan 1',
            'quantity' => 9, 'term_months' => 12, 'interval' => 'yearly', 'renewal' => 'auto', 'status' => 'active', 'currency' => 'EUR',
        ], $attributes));
    }

    public function test_carried_assignment_grows_an_existing_one_and_takes_the_next_free_suffix(): void {
        $logged = [];
        Log::listen(static function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event->message;
        });
        $schub = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei']);
        $maerkische = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Märkische Bunker']);
        $third = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Dritte GmbH']);
        $contract = $this->subscription(['provider' => 'telekom_marketplace', 'external_id' => 'ent-9', 'customer_id' => $schub->id, 'starts_on' => '2024-10-18', 'ends_on' => '2025-10-18', 'renewal' => 'cancel', 'status' => 'cancelled']);
        $this->subscription(['provider' => 'telekom_marketplace', 'parent_id' => $contract->id, 'external_id' => 'ent-9#1', 'customer_id' => $maerkische->id, 'quantity' => 5, 'starts_on' => '2024-10-18']);
        $this->subscription(['provider' => 'telekom_marketplace', 'parent_id' => $contract->id, 'external_id' => 'ent-9#2', 'customer_id' => $third->id, 'quantity' => 2, 'starts_on' => '2025-01-01']);
        $successor = $this->subscription(['provider' => 'qualityhosting', 'external_id' => 'CNLCON1', 'customer_id' => $schub->id, 'starts_on' => '2025-10-18']);
        // Am Nachfolger gibt es schon eine (manuelle) Abtretung an Märkische mit Suffix #3 — #1/#2 wurden gelöscht.
        $existing = $this->subscription(['provider' => 'qualityhosting', 'parent_id' => $successor->id, 'external_id' => 'CNLCON1#3', 'customer_id' => $maerkische->id, 'quantity' => 1, 'starts_on' => '2025-10-18']);

        $importer = app(MarketplaceImporter::class);
        $method = new \ReflectionMethod($importer, 'carryAssignments');
        $method->invoke($importer, $contract, $successor, CarbonImmutable::parse('2026-09-08'));

        $this->assertSame(6, $existing->fresh()?->quantity, 'vorhandene Abtretung wächst um die 5 des Vorgängers — nichts geht verloren');
        $carried = ResaleSubscription::query()->where('parent_id', $successor->id)->where('customer_id', $third->id)->firstOrFail();
        $this->assertSame('CNLCON1#4', $carried->external_id, 'höchster Suffix + 1, nicht Anzahl + 1 (#2 gäbe es nach dem Löschen wieder)');
        $this->assertSame(2, $carried->quantity);
        $this->assertSame(2, ResaleSubscription::query()->where('parent_id', $successor->id)->count());
        $this->assertSame([SubscriptionStatus::Superseded, SubscriptionStatus::Superseded], $contract->assignments()->pluck('status')->all(), 'Vorgänger-Abtretungen enden mit dem Vorgänger');
        $this->assertSame([1, 1], $successor->periods()->pluck('quantity')->all(), '9 − 6 − 2 = 1 beim Nachfolger selbst');
        $this->assertCount(1, array_filter($logged, static fn(string $m): bool => str_starts_with($m, 'MarketplaceImporter:')), 'Erhöhung im Log vermerkt');

        // Zweiter Durchlauf: nichts doppelt, nichts nochmals addiert.
        $method->invoke($importer, $contract, $successor, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(6, $existing->fresh()?->quantity);
        $this->assertSame(2, ResaleSubscription::query()->where('parent_id', $successor->id)->count());
    }

    public function test_reimport_keeps_a_maintained_purchase_price_and_derives_status_from_the_reference_day(): void {
        $csv = <<<'CSV'
        Kennung;Firma;Produkt;Menge;Beginn;Ende;Intervall;Laufzeit (Monate);Einkaufspreis;Verkaufspreis;Anbieter
        HST-1;Beispiel GmbH;Hosted Exchange Postfach;4;01.03.2025;30.09.2026;jährlich;12;39,84;47,40;
        CSV;
        $records = $this->importGeneric($csv, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(1, $records[0]->rows_created);
        $subscription = ResaleSubscription::query()->where('external_id', 'HST-1')->firstOrFail();
        $this->assertSame('39.8400', $subscription->purchase_unit_price?->getAmount());
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->status);

        // Einkaufspreis von Hand gepflegt → der nächste Import lässt ihn stehen, auch bei geänderter Zeile.
        $subscription->forceFill(['purchase_unit_price' => '40.00'])->save();
        $again = $this->importGeneric($csv, CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(1, $again[0]->rows_unchanged);
        $this->assertSame('40.0000', $subscription->fresh()?->purchase_unit_price?->getAmount());

        $changed = $this->importGeneric(str_replace(';4;01.03.2025', ';5;01.03.2025', $csv), CarbonImmutable::parse('2026-09-08'));
        $this->assertSame(1, $changed[0]->rows_updated);
        $this->assertSame(5, $subscription->fresh()?->quantity);
        $this->assertSame('40.0000', $subscription->fresh()?->purchase_unit_price?->getAmount(), 'gepflegter Einkaufspreis überlebt die Hash-Änderung');

        // Status hängt am Stichtag, nicht am Hash: nach dem Ende wird aus „gekündigt" „beendet" — als Änderung gezählt.
        $later = $this->importGeneric(str_replace(';4;01.03.2025', ';5;01.03.2025', $csv), CarbonImmutable::parse('2026-10-05'));
        $this->assertSame(1, $later[0]->rows_updated);
        $this->assertSame(SubscriptionStatus::Ended, $subscription->fresh()?->status);
    }

    public function test_backfilled_older_price_list_does_not_overlap_and_keeps_products_only_it_knows(): void {
        // Review 2026-09-10 (B11): Liste 2026-01 zuerst, dann 2025-06 nachgereicht.
        $importer = app(MarketplaceImporter::class);
        $importer->import($this->organization, null, [ResaleImport::KIND_PRICELIST => ['name' => 'p1.xlsx', 'path' => $this->priceList('01.01.2026', [['Microsoft 365 Business Premium', 15.66, 19.06], ['Exchange Online Plan 1', 2.86, 3.5]])]]);
        $importer->import($this->organization, null, [ResaleImport::KIND_PRICELIST => ['name' => 'p0.xlsx', 'path' => $this->priceList('01.06.2025', [['Microsoft 365 Business Premium', 14.00, 18.00], ['Microsoft 365 Apps for Business', 6.00, 8.00]])]]);

        $today = CarbonImmutable::parse('2026-09-08');
        $valid = ResalePriceEntry::query()->validOn($today)->orderBy('product')->get();
        $this->assertSame(['Exchange Online Plan 1', 'Microsoft 365 Apps for Business', 'Microsoft 365 Business Premium'], $valid->pluck('product')->all(), 'jedes Produkt genau einmal gültig');
        $premium = $valid->firstWhere('product', 'Microsoft 365 Business Premium');
        $this->assertSame('2026-01-01', $premium?->valid_from->toDateString(), 'die jüngere Liste gilt heute');
        $this->assertSame('187.9200', $premium?->purchase_unit_price->getAmount());
        $this->assertNull($valid->firstWhere('product', 'Microsoft 365 Apps for Business')?->valid_to, 'Produkt nur in der alten Liste → bleibt gültig');

        $olderPremium = ResalePriceEntry::query()->where('product', 'Microsoft 365 Business Premium')->where('valid_from', '<', '2026-01-01')->firstOrFail();
        $this->assertSame('2025-12-31', $olderPremium->valid_to?->toDateString(), 'nachgereichte Zeile endet am Vortag der jüngeren');

        // Eine dritte Liste (2026-03) nur mit Premium schließt nur Premium; Exchange und Apps bleiben offen.
        $importer->import($this->organization, null, [ResaleImport::KIND_PRICELIST => ['name' => 'p2.xlsx', 'path' => $this->priceList('01.03.2026', [['Microsoft 365 Business Premium', 16.00, 20.00]])]]);
        $this->assertSame('2026-02-28', ResalePriceEntry::query()->where('product', 'Microsoft 365 Business Premium')->where('valid_from', '>=', '2026-01-01')->where('valid_from', '<', '2026-01-02')->first()?->valid_to?->toDateString());
        $valid = ResalePriceEntry::query()->validOn($today)->orderBy('product')->get();
        $this->assertCount(3, $valid);
        $this->assertSame('192.0000', $valid->firstWhere('product', 'Microsoft 365 Business Premium')?->purchase_unit_price->getAmount());
        $this->assertNull($valid->firstWhere('product', 'Exchange Online Plan 1')?->valid_to);
        $this->assertSame('2026-01-01', $valid->firstWhere('product', 'Exchange Online Plan 1')?->valid_from->toDateString());

        // Dieselbe Liste nochmal: idempotent.
        $importer->import($this->organization, null, [ResaleImport::KIND_PRICELIST => ['name' => 'p2.xlsx', 'path' => $this->priceList('01.03.2026', [['Microsoft 365 Business Premium', 16.00, 20.00]])]]);
        $this->assertSame(5, ResalePriceEntry::query()->count());
        $this->assertCount(3, ResalePriceEntry::query()->validOn($today)->get());
    }

    public function test_domain_provider_rows_are_rejected_with_an_issue_that_reaches_the_console(): void {
        // Review 2026-09-10 (B7/A1): Domains nur über den Sync; Befunde je Zeile in Modell und Konsole.
        $csv = <<<'CSV'
        Kennung;Firma;Produkt;Menge;Beginn;Ende;Intervall;Laufzeit (Monate);Einkaufspreis;Verkaufspreis;Anbieter
        HST-1;Beispiel GmbH;Hosted Exchange Postfach;4;01.03.2025;;jährlich;12;39,84;47,40;
        beispiel.de;Beispiel GmbH;Domain .de;1;01.03.2025;;jährlich;12;8,87;15,00;domainreselling
        ;Beispiel GmbH;Backup;1;kein datum;;;;;;
        CSV;
        $records = $this->importGeneric($csv);
        $record = $records[0]->fresh();
        $this->assertNotNull($record);
        $this->assertSame(1, $record->rows_created);
        $this->assertSame(2, $record->issueCount(), 'unlesbares Datum + abgelehnter Domain-Anbieter');
        $this->assertCount(1, $record->issuesPreview(1));
        $this->assertStringContainsString('Domain', implode(' ', $record->issuesPreview()));
        $this->assertSame(0, ResaleSubscription::query()->where('provider', SubscriptionProvider::DomainReselling->value)->count(), 'kein Domain-Abo aus der Datei');
        $this->assertSame(1, ResaleSubscription::query()->count());

        $this->artisan('resale:import', ['--org' => $this->organization->id, '--generic' => $this->csv($csv), '--provider' => 'manual'])
            ->expectsOutputToContain('2 Befunde')
            ->expectsOutputToContain('(Beispiel GmbH, Domain .de)')
            ->assertSuccessful();
    }
}
