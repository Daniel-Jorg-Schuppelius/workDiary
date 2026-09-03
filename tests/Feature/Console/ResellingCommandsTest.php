<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResellingCommandsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\{Customer, ExternalReference};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Feature\Reselling\MarketplaceReconcilerTest;
use Tests\Support\{FakePluginHttp, InteractsWithPlugins};
use Tests\TestCase;
use Tests\Unit\Reselling\QualityHostingContractsReaderTest;

class ResellingCommandsTest extends TestCase {
    use InteractsWithPlugins;
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_reconcile_marketplace_reports_findings_and_writes_csv(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key']);
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
        MarketplaceReconcilerTest::fakeLexoffice();

        $csv = sys_get_temp_dir() . '/reselling-' . uniqid() . '/bericht.csv';
        $xlsx = QualityHostingContractsReaderTest::writeFixture();

        $this->artisan('reselling:reconcile-marketplace', [
            'purchases' => [MarketplaceReconcilerTest::FIXTURE, $xlsx],
            '--organization' => $this->organization->id,
            '--until' => '2025-01-01',
            '--csv' => $csv,
            '--all' => true,
        ])
            // Je Ausgabezeile nur EINE Erwartung: Laravel bindet jeden Schreibaufruf
            // an die erste passende Mockery-Erwartung, eine zweite zur selben Zeile
            // bliebe unerfüllt.
            ->expectsOutputToContain('8 Positionen (Telekom 5, Quality Hosting 3) · 4 Firmen · 2 Ablösungen')
            ->expectsOutputToContain('Ablösungen Telekom → Quality Hosting')
            ->expectsOutputToContain('Wöchentlich')
            ->expectsOutputToContain('Unter Einkauf')
            ->expectsOutputToContain('Fehlt')
            ->expectsOutputToContain('Offene Einkaufsgebühr (fehlend/teilweise): 664,86 €')
            ->expectsOutputToContain('Ohne Zuordnung: 2 Firmen')
            ->assertExitCode(0);

        $this->assertFileExists($csv);
        $content = (string) file_get_contents($csv);
        $this->assertStringStartsWith("\xEF\xBB\xBFFirma;Kunde;", $content);
        $this->assertStringContainsString('Muster Bau GmbH;Muster Bau GmbH;c-1;', $content);
        $this->assertStringContainsString(';Unter Einkauf;RE-2024-02;', $content);
        $this->assertStringContainsString('Beispiel Logistik;;c-2;Namenssuche (Name gleich);', $content);
        // Die QH-Verträge beginnen nach dem Stichtag — im Bericht steht nur die gekappte Telekom-Position.
        $this->assertStringContainsString(';Telekom;ent-0001;', $content);
        $this->assertStringContainsString('abgelöst durch Quality Hosting CNLCON00167 ab 02.08.2025', $content);
        // Kopf + 8 fällige Telekom-Perioden; die QH-Verträge beginnen nach dem Stichtag 2025-01-01.
        $this->assertSame(9, count(array_filter(explode("\n", $content))));

        @unlink($csv);
        @unlink($xlsx);
        @rmdir(dirname($csv));
    }

    public function test_reconcile_marketplace_stops_when_lexoffice_rejects_the_key(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'expired-key']);
        FakePluginHttp::fake([
            'https://api.lexoffice.io/v1/profile' => FakePluginHttp::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->artisan('reselling:reconcile-marketplace', [
            'purchases' => [MarketplaceReconcilerTest::FIXTURE],
            '--organization' => $this->organization->id,
        ])
            ->expectsOutputToContain('Lexoffice nicht erreichbar')
            ->assertExitCode(1);
    }

    public function test_reconcile_marketplace_fails_without_lexoffice(): void {
        $this->artisan('reselling:reconcile-marketplace', [
            'purchases' => [MarketplaceReconcilerTest::FIXTURE],
            '--organization' => $this->organization->id,
        ])
            ->expectsOutputToContain('Lexoffice-Plugin nicht aktiv')
            ->assertExitCode(1);
    }

    public function test_reconcile_marketplace_rejects_unreadable_file(): void {
        $this->enablePluginFor($this->organization, LexofficePlugin::ID, ['api_key' => 'lex-key']);

        $this->artisan('reselling:reconcile-marketplace', [
            'purchases' => ['/nicht/vorhanden.csv'],
            '--organization' => $this->organization->id,
        ])
            ->expectsOutputToContain('nicht lesbar')
            ->assertExitCode(1);
    }
}
