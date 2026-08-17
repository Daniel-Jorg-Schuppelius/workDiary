<?php
/*
 * Created on   : Sat Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleDatanormExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Models\{Article, User};
use ERechnungToolkit\Parsers\DatanormParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use ZipArchive;

/**
 * Feature 107 W5: DATANORM-Export des Artikelstamms — ZIP mit DATANORM.001 +
 * DATAINFO.TXT, Round-Trip über den Toolkit-Parser (V5 und V4), Preisquelle
 * VK als Listen- oder Nettopreis, Überlängen-Artikelnummern übersprungen.
 */
final class ArticleDatanormExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $group = \App\Models\SalesDiscountGroup::query()->create([
            'organization_id' => $this->organization->id,
            'code' => 'R10', 'kind' => 'discount', 'value' => '10', 'label' => 'Standardrabatt',
        ]);
        Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'WD-1001', 'name' => 'Wartung Heizungsanlage', 'status' => 'active',
            'sellable' => true, 'base_unit' => 'Std', 'default_sale_price' => '89.5',
            'gtin' => '4012345678901', 'description' => "Jährliche Wartung inkl. Protokoll.\nAnfahrt gesondert.",
            'category' => 'Heizung', 'subcategory' => 'Wartung',
            'sales_discount_group_id' => $group->id,
        ]);
        Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'WD-1002', 'name' => 'Kleinmaterial', 'status' => 'active',
            'sellable' => true, 'base_unit' => 'Stk', 'default_sale_price' => '3.25',
        ]);
        // Nicht verkäuflich → nicht im Export.
        Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'WD-9999', 'name' => 'Interner Artikel', 'status' => 'active', 'sellable' => false,
        ]);
        // Artikelnummer > 15 Zeichen → übersprungen, in DATAINFO gemeldet.
        Article::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'VIEL-ZU-LANGE-NUMMER-123', 'name' => 'Zu lang', 'status' => 'active', 'sellable' => true,
        ]);
    }

    /** @return array{datanorm: string, datpreis: string, wrg: string, rab: string, datainfo: string} */
    private function download(array $query): array {
        $response = $this->actingAs($this->admin)->get(route('articles.export.datanorm', $query));
        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));

        $file = $response->getFile();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($file->getPathname()));
        $datanorm = (string) $zip->getFromName('DATANORM.001');
        $datpreis = (string) $zip->getFromName('DATPREIS.001');
        $wrg = (string) $zip->getFromName('DATANORM.WRG');
        $rab = (string) $zip->getFromName('DATANORM.RAB');
        $datainfo = (string) $zip->getFromName('DATAINFO.TXT');
        $zip->close();

        return ['datanorm' => $datanorm, 'datpreis' => $datpreis, 'wrg' => $wrg, 'rab' => $rab, 'datainfo' => $datainfo];
    }

    public function test_v5_export_round_trips_through_the_parser(): void {
        $files = $this->download(['version' => 5, 'prices' => 'list']);

        $catalog = (new DatanormParser)->parse($files['datanorm']);

        $this->assertSame([], $catalog->getWarnings());
        $this->assertCount(2, $catalog->getArticles());

        $wartung = $catalog->getArticles()[0];
        $this->assertSame('WD-1001', $wartung->getArticleNumber());
        $this->assertSame('Wartung Heizungsanlage', $wartung->getName());
        $this->assertSame('HUR', $wartung->getUnit());
        $this->assertSame(1, $wartung->getPriceIndicator()->value); // Listenpreis
        $this->assertSame('89.50', $wartung->getPrice()?->getAmount());
        $this->assertSame('4012345678901', $wartung->getEan());
        $this->assertStringContainsString('Jährliche Wartung inkl. Protokoll.', (string) $wartung->getLongText());

        $this->assertSame('PCE', $catalog->getArticles()[1]->getUnit());
        $this->assertStringContainsString('VIEL-ZU-LANGE-NUMMER-123', $files['datainfo']);

        // W8: Kategorie → Warengruppen-Codes im A-Satz + WRG-Datei mit Labels.
        $this->assertSame('HEI', $wartung->getMainProductGroup());
        $this->assertSame('WARTUNG', $wartung->getProductGroup());
        $wrg = (new DatanormParser)->parse($files['wrg']);
        $this->assertSame('Heizung', $wrg->resolveProductGroupLabel('HEI', null));
        $this->assertSame('Wartung', $wrg->resolveProductGroupLabel('HEI', 'WARTUNG'));

        // W9: Verkaufs-Rabattgruppe im A-Satz + RAB-Datei.
        $this->assertSame('R10', $wartung->getDiscountGroup());
        $rab = (new DatanormParser)->parse($files['rab']);
        $this->assertSame(10.0, $rab->getDiscountGroup('R10')?->getValue());

        // Preislisten-Abfluss ist auditiert.
        $this->assertSame(1, \App\Models\AuditLog::query()->where('event', 'datanorm.exported')->count());
    }

    public function test_datpreis_since_exports_only_changed_prices(): void {
        // Anlage-Historie liegt „jetzt"; 40 Tage später ändert sich nur WD-1002.
        $this->travelTo(now()->addDays(40));
        Article::query()->where('number', 'WD-1002')->firstOrFail()
            ->update(['default_sale_price' => '3.75']);

        $files = $this->download(['type' => 'prices', 'version' => 5, 'prices' => 'list', 'since_days' => 30]);
        $catalog = (new DatanormParser)->parse($files['datpreis']);

        $this->assertCount(1, $catalog->getPriceChanges());
        $this->assertSame('WD-1002', $catalog->getPriceChanges()[0]->getArticleNumber());
        $this->assertSame('3.75', $catalog->getPriceChanges()[0]->getPrice()?->getAmount());
        $this->travelBack();
    }

    public function test_b2b_datpreis_applies_group_and_customer_override(): void {
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        [$access] = \App\Models\B2b\B2bCatalogAccess::issue((int) $this->organization->id, (int) $customer->id, 'Konditionen', 'einkauf-kond');
        // WD-1001 (VK 89,50, Gruppe R10 = 10 %) OHNE custom_price freigeben.
        \App\Models\B2b\B2bCatalogItem::query()->create([
            'organization_id' => $this->organization->id, 'access_id' => $access->id,
            'article_id' => Article::query()->where('number', 'WD-1001')->firstOrFail()->id,
        ]);

        // Standardsatz der Gruppe: 89,50 − 10 % = 80,55.
        $response = $this->actingAs($this->admin)->get(route('b2b-catalog.datanorm', $access));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $catalog = (new DatanormParser)->parse((string) $zip->getFromName('DATPREIS.001'));
        $zip->close();
        $this->assertSame('80.55', $catalog->getPriceChanges()[0]->getPrice()?->getAmount());

        // Kunden-Override 20 %: 89,50 − 20 % = 71,60.
        \App\Models\SalesDiscountGroupOverride::query()->create([
            'organization_id' => $this->organization->id,
            'sales_discount_group_id' => \App\Models\SalesDiscountGroup::query()->where('code', 'R10')->firstOrFail()->id,
            'customer_id' => $customer->id, 'kind' => 'discount', 'value' => '20',
        ]);
        $response = $this->actingAs($this->admin)->get(route('b2b-catalog.datanorm', $access));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $catalog = (new DatanormParser)->parse((string) $zip->getFromName('DATPREIS.001'));
        $zip->close();
        $this->assertSame('71.60', $catalog->getPriceChanges()[0]->getPrice()?->getAmount());
    }

    public function test_sales_discount_group_management_page(): void {
        $this->actingAs($this->admin)->get(route('articles.sales-discount-groups.index'))
            ->assertOk()->assertSee('R10');

        $this->actingAs($this->admin)->post(route('articles.sales-discount-groups.store'), [
            'code' => 'f5', 'kind' => 'factor', 'value' => '0.95', 'label' => 'Faktor 0,95',
        ])->assertRedirect();
        $this->assertSame('F5', \App\Models\SalesDiscountGroup::query()->where('label', 'Faktor 0,95')->firstOrFail()->code);
    }

    public function test_revoked_b2b_access_cannot_export_datpreis(): void {
        $customer = \App\Models\Customer::factory()->create(['organization_id' => $this->organization->id]);
        [$access] = \App\Models\B2b\B2bCatalogAccess::issue((int) $this->organization->id, (int) $customer->id, 'Widerrufen', 'einkauf-alt');
        $access->forceFill(['revoked_at' => now()])->save();

        $this->actingAs($this->admin)
            ->get(route('b2b-catalog.datanorm', $access))
            ->assertRedirect();
        $this->assertSame(0, \App\Models\AuditLog::query()->where('event', 'datanorm.exported')->count());
    }

    public function test_datpreis_export_contains_current_prices(): void {
        $files = $this->download(['type' => 'prices', 'version' => 5, 'prices' => 'list']);

        $this->assertNotSame('', $files['datpreis']);
        $catalog = (new DatanormParser)->parse($files['datpreis']);

        $this->assertSame([], $catalog->getWarnings());
        $this->assertCount(2, $catalog->getPriceChanges());
        $this->assertSame('WD-1001', $catalog->getPriceChanges()[0]->getArticleNumber());
        $this->assertSame(1, $catalog->getPriceChanges()[0]->getPriceIndicator()->value);
        $this->assertSame('89.50', $catalog->getPriceChanges()[0]->getPrice()?->getAmount());
    }

    public function test_b2b_access_exports_customer_specific_datpreis(): void {
        $customer = \App\Models\Customer::factory()->create([
            'organization_id' => $this->organization->id, 'company' => 'ACME GmbH',
        ]);
        [$access] = \App\Models\B2b\B2bCatalogAccess::issue((int) $this->organization->id, (int) $customer->id, 'ACME Einkauf', 'einkauf-acme');
        // Freigabe mit kundenindividuellem Preis + Freigabe zum Standard-VK.
        \App\Models\B2b\B2bCatalogItem::query()->create([
            'organization_id' => $this->organization->id, 'access_id' => $access->id,
            'article_id' => Article::query()->where('number', 'WD-1001')->firstOrFail()->id,
            'custom_price' => '79.9',
        ]);
        \App\Models\B2b\B2bCatalogItem::query()->create([
            'organization_id' => $this->organization->id, 'access_id' => $access->id,
            'article_id' => Article::query()->where('number', 'WD-1002')->firstOrFail()->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('b2b-catalog.datanorm', $access));
        $response->assertOk();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $content = (string) $zip->getFromName('DATPREIS.001');
        $zip->close();

        $catalog = (new DatanormParser)->parse($content);
        // K-Kontrollsatz trägt die Kundennummer des Empfängers.
        $this->assertSame($customer->number, $catalog->getCustomer()?->getCustomerNumber());
        $this->assertSame('ACME GmbH', $catalog->getCustomer()?->getName());

        $this->assertCount(2, $catalog->getPriceChanges());
        // Kundenpreis schlägt den Standard-VK; alles Nettopreise (Kennzeichen 2).
        $this->assertSame('79.90', $catalog->getPriceChanges()[0]->getPrice()?->getAmount());
        $this->assertSame(2, $catalog->getPriceChanges()[0]->getPriceIndicator()->value);
        $this->assertSame('3.25', $catalog->getPriceChanges()[1]->getPrice()?->getAmount());
    }

    public function test_v4_export_and_net_prices(): void {
        $files = $this->download(['version' => 4, 'prices' => 'net']);

        $lines = explode("\r\n", $files['datanorm']);
        $this->assertSame(128, strlen($lines[0])); // Festbreiten-Vorlaufsatz
        $this->assertSame('04EUR', substr($lines[0], 123, 5));

        $catalog = (new DatanormParser)->parse($files['datanorm']);
        $this->assertCount(2, $catalog->getArticles());
        $this->assertSame(2, $catalog->getArticles()[0]->getPriceIndicator()->value); // Nettopreis
        $this->assertSame('89.50', $catalog->getArticles()[0]->getPrice()?->getAmount());
        // V4-Einheit als Freitext gemappt (HUR → Std).
        $this->assertSame('Std', $catalog->getArticles()[0]->getUnit());
    }
}
