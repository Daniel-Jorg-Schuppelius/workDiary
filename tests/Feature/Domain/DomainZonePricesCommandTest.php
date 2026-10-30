<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainZonePricesCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Console\Commands\Domain\DomainContractCommand;
use App\Models\Domain\{DomainProviderConnection, DomainResellerAccount};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Vertragsprobe der Zonenpreise (Feature 185, 2026-10-30).
 */
class DomainZonePricesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_probe_shows_both_price_commands_and_records_their_contract(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'sub-standard', 'user_class' => 'STANDARD']);
        DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'sub-reseller', 'user_class' => 'RESELLER']);
        $seen = [];
        FakeDomainResellingTransport::fake([
            'QueryZoneList' => static function (array $params) use (&$seen): string {
                $seen['QueryZoneList'] = $params;

                return FakeDomainResellingTransport::properties([
                    ['zone' => 'de', 'periodtype' => 'YEAR', 'setup' => '0.00', 'annual' => '4.50', 'transfer' => '4.50', 'currency' => 'EUR', 'domain_count' => '116', 'active' => '1'],
                    ['zone' => 'com', 'periodtype' => 'YEAR', 'setup' => '0.00', 'annual' => '11.91', 'transfer' => '11.91', 'currency' => 'EUR', 'domain_count' => '15', 'active' => '1'],
                ]);
            },
            'QueryZonePriceList' => static function (array $params) use (&$seen): string {
                $seen['QueryZonePriceList'][] = $params;

                return FakeDomainResellingTransport::properties([
                    ['pricescale' => (string) ($params['PRICESCALE'] ?? ''), 'zone' => 'de', 'period' => '1', 'periodtype' => 'YEAR', 'annual' => $params['PRICESCALE'] === 'STANDARD' ? '8.94' : '9.47', 'currency' => 'EUR'],
                ]);
            },
        ]);

        $this->assertSame(0, Artisan::call('domain:zone-prices', ['--zone' => 'de']));
        $output = Artisan::output();

        $this->assertStringContainsString('QueryZoneList ZONE=de', $output);
        $this->assertStringContainsString('annual', $output);
        $this->assertStringContainsString('4.50', $output);
        $this->assertStringContainsString('QueryZonePriceList PRICESCALE=RESELLER', $output, 'Staffeln aus den Klassen der Subuser');
        $this->assertStringContainsString('QueryZonePriceList PRICESCALE=STANDARD', $output);
        $this->assertStringContainsString('8.94', $output);
        $this->assertSame('de', $seen['QueryZoneList']['ZONE']);
        $this->assertSame(['RESELLER', 'STANDARD'], array_column($seen['QueryZonePriceList'], 'PRICESCALE'));
        $this->assertSame('1', $seen['QueryZonePriceList'][0]['WIDE']);

        $observed = $connection->refresh()->observed_properties ?? [];
        $this->assertContains('annual', $observed['queryzonelist'] ?? [], 'gelieferte Eigenschaften für domain:contract aufgezeichnet');
        $this->assertContains('pricescale', $observed['queryzonepricelist'] ?? []);
        foreach (['queryzonelist', 'queryzonepricelist'] as $command) {
            foreach (DomainContractCommand::EXPECTED[$command] as $label => $candidates) {
                $this->assertTrue(DomainContractCommand::delivers($observed[$command], $candidates), sprintf('%s: %s gilt laut Doku als geliefert', $command, $label));
            }
        }
    }

    public function test_explicit_scale_and_registrar_are_passed_through(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $seen = [];
        FakeDomainResellingTransport::fake([
            'QueryZoneList' => static function (array $params) use (&$seen): string {
                $seen['QueryZoneList'] = $params;

                return FakeDomainResellingTransport::properties([['zone' => 'de', 'annual' => '8.94', 'currency' => 'EUR']]);
            },
            'QueryZonePriceList' => static function (array $params) use (&$seen): string {
                $seen['QueryZonePriceList'][] = $params;

                return FakeDomainResellingTransport::properties([['zone' => 'de', 'annual' => '8.94', 'currency' => 'EUR']]);
            },
        ]);

        $this->assertSame(0, Artisan::call('domain:zone-prices', ['--registrar' => 'sub-standard', '--scale' => ['gold']]));

        $this->assertSame('sub-standard', $seen['QueryZoneList']['REGISTRAR'], 'wirksame Preise des Subusers');
        $this->assertSame(['GOLD', null], array_map(static fn (array $p): ?string => $p['PRICESCALE'] ?? null, $seen['QueryZonePriceList']));
        $this->assertSame('sub-standard', $seen['QueryZonePriceList'][1]['REGISTRAR']);
    }

    public function test_gateway_error_is_shown_and_fails_the_command(): void {
        DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        FakeDomainResellingTransport::fake(['QueryZoneList' => "code=530\ndescription=Authentication failed\nEOF\n"]);

        $this->assertSame(1, Artisan::call('domain:zone-prices'));
        $this->assertStringContainsString('Antwort 530 Authentication failed', Artisan::output());
    }
}
