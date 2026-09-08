<?php
/*
 * Created on   : Tue Sep 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GenericImportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reselling;

use App\Enums\Reselling\{BillingFrequency, SubscriptionProvider, SubscriptionStatus};
use App\Models\Customer;
use App\Models\Reselling\{ResaleImport, ResaleSubscription};
use App\Services\Reselling\Marketplace\GenericSubscriptionReader;
use App\Services\Reselling\Register\MarketplaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Generische Abo-Liste (Feature 152): Spalten am Namen erkannt, deutsche
 * Zahlen und Daten, Kennung aus Firma/Produkt/Beginn, Anbieter aus Spalte
 * oder Dialog, Verkaufspreis aus der Liste, Wiederholung ohne Dubletten.
 */
class GenericImportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const CSV = <<<'CSV'
    Kennung;Firma;Produkt;Menge;Beginn;Ende;Intervall;Laufzeit (Monate);Einkaufspreis;Verkaufspreis;Anbieter;Bestellnummer
    HST-4711;Schub- und Schleppreederei U. Golka GmbH & CO. KG;Hosted Exchange Postfach;4;01.03.2025;;jährlich;12;39,84;47,40;;B-1
    ;Märkische Bunker- und Service GmbH & Co. KG;Microsoft 365 Business Standard;2;15.03.2025;14.03.2027;monatlich;;10,70 €;12,13 €;Telekom Cloud Marketplace;
    ;Beispiel GmbH;Backup;1;kein datum;;;;;;;
    CSV;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake(ResaleImport::DISK);
        $this->travelTo('2026-09-08');
    }

    private function csv(): string {
        $path = sys_get_temp_dir() . '/generic-' . uniqid() . '.csv';
        file_put_contents($path, "\xEF\xBB\xBF" . self::CSV);

        return $path;
    }

    public function test_reader_recognises_german_columns_and_derives_ids(): void {
        $path = $this->csv();
        try {
            $import = (new GenericSubscriptionReader)->read($path, SubscriptionProvider::Manual);
        } finally {
            @unlink($path);
        }
        $this->assertCount(2, $import->entitlements);
        $this->assertCount(1, $import->issues, 'Zeile ohne lesbaren Beginn wird gemeldet');
        [$schub, $maerkische] = $import->entitlements;
        $this->assertSame('HST-4711', $schub->entitlementId);
        $this->assertSame(4, $schub->quantity);
        $this->assertSame('39.8400', $schub->unitFee?->withScale(4)->getAmount());
        $this->assertSame('47.40', $schub->salePrice?->withScale(2)->getAmount());
        $this->assertSame(BillingFrequency::Yearly, $schub->frequency);
        $this->assertSame('manual', $schub->provider, 'ohne Spaltenwert gilt der Dialog-Anbieter');
        $this->assertNull($schub->endsOn);
        $this->assertStringStartsWith('gen:', $maerkische->entitlementId, 'Kennung aus Firma, Produkt und Beginn');
        $this->assertSame('telekom_marketplace', $maerkische->provider);
        $this->assertSame(BillingFrequency::Monthly, $maerkische->frequency);
        $this->assertSame('2027-03-14', $maerkische->endsOn?->toDateString());
        $this->assertSame('10.7000', $maerkische->unitFee?->withScale(4)->getAmount(), '„10,70 €" gelesen');
    }

    public function test_import_creates_subscriptions_resolves_holders_and_is_idempotent(): void {
        Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Schub- und Schleppreederei U. Golka GmbH & CO. KG']);
        $path = $this->csv();
        try {
            $records = app(MarketplaceImporter::class)->import($this->organization, null, [ResaleImport::KIND_GENERIC => ['name' => 'liste.csv', 'path' => $path]], null, SubscriptionProvider::Manual);
            $this->assertCount(1, $records);
            $this->assertSame(ResaleImport::KIND_GENERIC, $records[0]->kind);
            $this->assertSame(2, $records[0]->rows_created);
            $this->assertSame(1, $records[0]->rows_unassigned, 'Märkische ist kein Kunde → Inbox');

            $schub = ResaleSubscription::query()->where('external_id', 'HST-4711')->firstOrFail();
            $this->assertSame(SubscriptionProvider::Manual, $schub->provider);
            $this->assertSame('Hosted Exchange Postfach', $schub->label);
            $this->assertSame(4, $schub->quantity);
            $this->assertSame('47.4000', $schub->sale_unit_price?->getAmount(), 'Verkaufspreis aus der Liste');
            $this->assertSame(SubscriptionStatus::Active, $schub->status);
            $this->assertNotNull($schub->customer_id, 'Halter über den Firmennamen');
            $this->assertCount(2, $schub->periods, 'März 2025 und März 2026');

            $maerkische = ResaleSubscription::query()->where('provider', SubscriptionProvider::TelekomMarketplace->value)->firstOrFail();
            $this->assertNull($maerkische->customer_id);
            $this->assertSame(SubscriptionStatus::Cancelled, $maerkische->status, 'Ende 2027 bekannt');

            // Zweiter Lauf: nichts Neues, nichts doppelt.
            $again = app(MarketplaceImporter::class)->import($this->organization, null, [ResaleImport::KIND_GENERIC => ['name' => 'liste.csv', 'path' => $path]], null, SubscriptionProvider::Manual);
            $this->assertSame(0, $again[0]->rows_created);
            $this->assertSame(2, $again[0]->rows_unchanged);
            $this->assertSame(2, ResaleSubscription::query()->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_dialog_accepts_the_generic_list_and_offers_a_template(): void {
        $admin = $this->orgAdmin();
        $this->actingAs($admin)->get(route('finance.resale.import.create'))->assertOk()->assertSee(__('resale.import.generic'))->assertSee(route('finance.resale.import.template'));
        $template = $this->actingAs($admin)->get(route('finance.resale.import.template'))->assertOk();
        $this->assertStringContainsString('Kennung;Firma;Produkt', $template->streamedContent());

        $this->actingAs($admin)->post(route('finance.resale.import.store'), [
            'generic' => UploadedFile::fake()->createWithContent('liste.csv', "\xEF\xBB\xBF" . self::CSV),
            'generic_provider' => 'manual',
        ])->assertRedirect(route('finance.resale.inbox'))->assertSessionHas('success');
        $this->assertSame(2, ResaleSubscription::query()->count());
        $this->assertSame(1, ResaleImport::query()->where('kind', ResaleImport::KIND_GENERIC)->count());
    }
}
