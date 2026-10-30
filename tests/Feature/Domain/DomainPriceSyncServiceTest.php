<?php
/*
 * Created on   : Fri Oct 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainPriceSyncServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Domain\DomainProviderConnection;
use App\Models\Reselling\ResalePriceEntry;
use App\Services\Domain\DomainPriceSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeDomainResellingTransport;
use Tests\TestCase;

/**
 * Preisstaffeln des Domainanbieters im Preiskatalog (Feature 185, 2026-10-30).
 */
class DomainPriceSyncServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->travelTo('2026-10-30');
    }

    /** @param  array<string, string>  $relations */
    private static function relations(array $relations): string {
        $rows = [['user' => 'konto', 'account currency' => 'EUR']];
        foreach ($relations as $type => $value) {
            $rows[] = ['relation type' => $type, 'relation value' => $value];
        }

        return FakeDomainResellingTransport::properties($rows);
    }

    /** @param  array<string, array<string, string>>  $classPrices  Klasse → Relationen */
    private function fakeGateway(array $own, array $classPrices): void {
        FakeDomainResellingTransport::fake([
            'StatusUser' => self::relations($own),
            'QueryUserClassList' => FakeDomainResellingTransport::properties(array_map(static fn (string $class): array => ['user class' => $class], array_keys($classPrices))),
            'StatusUserclass' => static fn (array $params): string => self::relations($classPrices[(string) $params['userclass']] ?? []),
        ]);
    }

    /** @return array<string, array{purchase: string, list: string, from: string, to: ?string}> */
    private function catalog(): array {
        $rows = [];
        foreach (ResalePriceEntry::query()->orderBy('price_class')->orderBy('product')->orderBy('valid_from')->get() as $entry) {
            $rows[$entry->price_class . ' ' . $entry->product . ' ' . $entry->valid_from->toDateString()] = [
                'purchase' => $entry->purchase_unit_price?->withScale(2)->getAmount(), 'list' => $entry->list_unit_price?->withScale(2)->getAmount(),
                'from' => $entry->valid_from->toDateString(), 'to' => $entry->valid_to?->toDateString(),
            ];
        }

        return $rows;
    }

    public function test_classes_become_catalog_rows_with_purchase_and_class_price_and_changes_open_new_rows(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $own = ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '4.50', 'PRICE_CLASS_DOMAIN_DE_CURRENCY' => 'EUR', 'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '11.91', 'PRICE_CLASS_DOMAIN_COM_FACTOR' => '', 'PRICE_CLASS_DOMAIN_ABCBR_CURRENCY' => 'EUR', 'ZONES' => 'de,com'];
        $this->fakeGateway($own, [
            'STANDARD' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '8.10', 'PRICE_CLASS_DOMAIN_DE_FACTOR' => '1.80', 'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '21.44', 'PRICE_CLASS_DOMAIN_ABCBR_ANNUAL' => '99.00'],
            'RESELLER' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '6.30'],
        ]);

        $result = app(DomainPriceSyncService::class)->sync($connection);

        $this->assertSame(['STANDARD', 'RESELLER'], $result['classes']);
        $this->assertSame(3, $result['zones'], 'Zonen mit eigenen Relationen, auch ohne Jahrespreis');
        $this->assertSame(3, $result['created']);
        $this->assertSame([
            'RESELLER .de 2026-10-30' => ['purchase' => '4.50', 'list' => '6.30', 'from' => '2026-10-30', 'to' => null],
            'STANDARD .com 2026-10-30' => ['purchase' => '11.91', 'list' => '21.44', 'from' => '2026-10-30', 'to' => null],
            'STANDARD .de 2026-10-30' => ['purchase' => '4.50', 'list' => '8.10', 'from' => '2026-10-30', 'to' => null],
        ], $this->catalog(), 'je Klasse und Zone eine Zeile; ohne eigenen oder ohne Klassenpreis keine (ABCBR, RESELLER .com)');

        // Zweiter Lauf am selben Tag: nichts zu tun.
        $again = app(DomainPriceSyncService::class)->sync($connection);
        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 3], array_intersect_key($again, ['created' => 1, 'updated' => 1, 'unchanged' => 1]));

        // Preisänderung am Folgetag: alte Zeile endet am Vortag, neue beginnt heute; .com bleibt.
        $this->travelTo('2026-11-15');
        $this->fakeGateway($own, [
            'STANDARD' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '8.55', 'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '21.44'],
            'RESELLER' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '6.30'],
        ]);
        $changed = app(DomainPriceSyncService::class)->sync($connection, CarbonImmutable::parse('2026-11-15'));
        $this->assertSame(1, $changed['updated']);
        $this->assertSame(2, $changed['unchanged']);
        $catalog = $this->catalog();
        $this->assertSame('2026-11-14', $catalog['STANDARD .de 2026-10-30']['to'], 'alte Zeile endet am Vortag');
        $this->assertSame(['purchase' => '4.50', 'list' => '8.55', 'from' => '2026-11-15', 'to' => null], $catalog['STANDARD .de 2026-11-15']);

        // Zweite Änderung am selben Tag: in der Zeile.
        $this->fakeGateway($own, [
            'STANDARD' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '8.60', 'PRICE_CLASS_DOMAIN_COM_ANNUAL' => '21.44'],
            'RESELLER' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '6.30'],
        ]);
        app(DomainPriceSyncService::class)->sync($connection, CarbonImmutable::parse('2026-11-15'));
        $catalog = $this->catalog();
        $this->assertSame('8.60', $catalog['STANDARD .de 2026-11-15']['list']);
        $this->assertCount(4, $catalog, 'keine dritte .de-Zeile');
    }

    public function test_command_reports_counts_and_fails_on_gateway_errors(): void {
        $connection = DomainProviderConnection::factory()->create(['organization_id' => $this->organization->id]);
        $this->fakeGateway(['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '4.50'], ['STANDARD' => ['PRICE_CLASS_DOMAIN_DE_ANNUAL' => '8.10']]);

        $this->assertSame(0, Artisan::call('domain:sync-prices'));
        $this->assertStringContainsString('1 Zonen, Klassen STANDARD — 1 Zeilen neu, 0 geändert, 0 unverändert', Artisan::output());
        $this->assertSame(1, ResalePriceEntry::query()->count());

        FakeDomainResellingTransport::fake(['StatusUser' => "code=530\ndescription=Authentication failed\nEOF\n"]);
        $this->assertSame(1, Artisan::call('domain:sync-prices', ['--connection' => $connection->id]), 'ein Fehlercode des Gateways bricht ab, statt leere Preise zu spiegeln');
        $this->assertStringContainsString('StatusUser: 530 Authentication failed', Artisan::output());
        $this->assertSame(1, ResalePriceEntry::query()->count(), 'Bestand bleibt');
    }
}
