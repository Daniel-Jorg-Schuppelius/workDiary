<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainPricesCommandTest.php
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
 * Vertragsprobe der Domainpreise aus den Benutzerrelationen (Feature 185, 2026-10-30).
 */
class DomainPricesCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * StatusUser-Antwort wie im Handbuch (Abschnitt 1.3.1.5): Kopfzeile plus Relationspaare.
     *
     * @param  array<string, string>  $prices  Relationstyp → Wert
     */
    private static function userResponse(string $user, ?string $class, array $prices): string {
        $rows = [['user' => $user, 'parent user' => 'upstream', 'account currency' => 'EUR', 'subusers direct' => '0']];
        if ($class !== null) {
            $rows[0]['userclass'] = $class;
        }
        foreach ($prices as $type => $value) {
            $rows[] = ['relation type' => $type, 'relation value' => $value];
        }

        return FakeDomainResellingTransport::properties($rows);
    }

    public function test_probe_reads_own_account_subusers_and_classes_and_records_the_contract(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'eigenes-konto', 'depth' => 0, 'user_class' => 'GOLD']);
        DomainResellerAccount::factory()->create(['organization_id' => $this->organization->id, 'connection_id' => $connection->id, 'external_user' => 'sub-standard', 'depth' => 1, 'user_class' => 'STANDARD']);
        $seen = [];
        FakeDomainResellingTransport::fake([
            'StatusUser' => static function (array $params) use (&$seen): string {
                $seen['StatusUser'][] = $params;
                if (($params['subuser'] ?? null) === 'sub-standard') {
                    return self::userResponse('sub-standard', 'STANDARD', ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '8.94', 'PRICE_CLASS_DOMAIN_DE_CURRENCY' => 'EUR']);
                }

                return self::userResponse('eigenes-konto', null, [
                    'PRICE_CLASS_DOMAIN_DE_ANNUAL' => '4.50', 'PRICE_CLASS_DOMAIN_DE_SETUP' => '1.00', 'PRICE_CLASS_DOMAIN_DE_TRANSFER' => '4.50', 'PRICE_CLASS_DOMAIN_DE_CURRENCY' => 'EUR',
                    'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '11.91', 'PRICE_CLASS_DOMAIN_COM_ANNUAL5' => '10.95', 'ZONES' => 'de,com', 'ALLOW_SUBUSER' => '1',
                ]);
            },
            'QueryUserClassList' => static fn (array $params): string => FakeDomainResellingTransport::properties([['userclass' => 'STANDARD'], ['userclass' => 'RESELLER']]),
            'StatusUserclass' => static function (array $params) use (&$seen): string {
                $seen['StatusUserclass'][] = $params;

                return self::userResponse('-', null, ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => $params['userclass'] === 'STANDARD' ? '8.94' : '9.47']);
            },
        ]);

        $this->assertSame(0, Artisan::call('domain:prices'));
        $output = Artisan::output();

        $this->assertStringContainsString('StatusUser (eigenes Konto)', $output);
        $this->assertStringContainsString('4.50', $output);
        $this->assertStringContainsString('ANNUAL5=10.95', $output, 'weitere Preisarten je Zone');
        $this->assertStringContainsString('8 Relationen, davon 2 ohne Domainpreis', $output);
        $this->assertStringContainsString('StatusUser subuser=sub-standard (Subuser sub-standard)', $output, 'nur Subuser, nicht das eigene Konto');
        $this->assertStringNotContainsString('subuser=eigenes-konto', $output);
        $this->assertStringContainsString('Klasse: STANDARD', $output);
        $this->assertStringContainsString('QueryUserClassList (Benutzerklassen)', $output);
        $this->assertStringContainsString('StatusUserclass userclass=RESELLER (Klasse RESELLER)', $output);
        $this->assertStringContainsString('9.47', $output);
        $this->assertSame([null, 'sub-standard'], array_map(static fn (array $p): ?string => $p['subuser'] ?? null, $seen['StatusUser']));
        $this->assertSame(['STANDARD', 'RESELLER'], array_column($seen['StatusUserclass'], 'userclass'));

        $observed = $connection->refresh()->observed_properties ?? [];
        $this->assertContains('relation type', $observed['statususer'] ?? [], 'gelieferte Eigenschaften für domain:contract aufgezeichnet');
        $this->assertContains('userclass', $observed['queryuserclasslist'] ?? []);
        foreach (['statususer', 'statususerclass', 'queryuserclasslist'] as $command) {
            foreach (DomainContractCommand::EXPECTED[$command] as $label => $candidates) {
                $this->assertTrue(DomainContractCommand::delivers($observed[$command], $candidates), sprintf('%s: %s gilt laut Handbuch als geliefert', $command, $label));
            }
        }
    }

    public function test_zone_filter_and_explicit_subusers_and_classes(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $seen = [];
        FakeDomainResellingTransport::fake([
            'StatusUser' => static function (array $params) use (&$seen): string {
                $seen['StatusUser'][] = $params;

                return self::userResponse('x', null, ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '4.50', 'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '11.91']);
            },
            'QueryUserClassList' => static function () use (&$seen): string {
                $seen['QueryUserClassList'] = true;

                return FakeDomainResellingTransport::properties([['userclass' => 'STANDARD']]);
            },
            'StatusUserclass' => static function (array $params) use (&$seen): string {
                $seen['StatusUserclass'][] = $params;

                return self::userResponse('-', null, ['PRICE_CLASS_DOMAIN_COM_ANNUAL' => '15.08']);
            },
        ]);

        $this->assertSame(0, Artisan::call('domain:prices', ['--zone' => 'com', '--subuser' => ['klimm-x'], '--class' => ['gold']]));
        $output = Artisan::output();

        $this->assertStringContainsString('11.91', $output);
        $this->assertStringNotContainsString('4.50', $output, 'Zonenfilter blendet .de aus');
        $this->assertStringContainsString('Zone COM', $output);
        $this->assertSame('klimm-x', $seen['StatusUser'][1]['subuser']);
        $this->assertArrayNotHasKey('QueryUserClassList', $seen, 'mit --class keine Klassenliste');
        $this->assertSame(['gold'], array_column($seen['StatusUserclass'], 'userclass'));
    }

    public function test_gateway_error_is_shown_and_fails_the_command(): void {
        DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        FakeDomainResellingTransport::fake(['StatusUser' => "code=500\ndescription=Invalid command name\nEOF\n"]);

        $this->assertSame(1, Artisan::call('domain:prices'));
        $this->assertStringContainsString('Antwort 500 Invalid command name', Artisan::output());
    }
}
