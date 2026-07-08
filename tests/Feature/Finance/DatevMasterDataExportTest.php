<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevMasterDataExportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Finance;

use App\Models\{Customer, User};
use App\Services\Finance\Datev\{DatevBookingConfig, DatevMasterDataExporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachtrag 045a: EXTF-Stammdatenexport Kategorie 16 (Debitoren) über den
 * Kategorie-16-Writer des php-financial-formats-Toolkits — Golden-Checks
 * auf Header (EXTF/Kategorie 16/Formatversion 5), CP1252, volle Spaltenzahl
 * und Kontologik (debtor_no vor Nummernkreis).
 */
final class DatevMasterDataExportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->organization->update([
            'settings' => array_replace((array) $this->organization->settings, [
                'datev' => ['advisor_number' => 12345, 'client_number' => 67890],
            ]),
        ]);
    }

    private function makeCustomer(array $overrides = []): Customer {
        return Customer::factory()->create(array_replace([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'company' => 'Muster GmbH',
            'vat_id' => 'DE123456789',
            'address_street' => 'Hauptstraße 1',
            'address_zip' => '12345',
            'address_city' => 'Berlin',
            'country' => 'DE',
            'number' => 'K-1001',
        ], $overrides));
    }

    public function test_generates_category16_extf_with_full_rows(): void {
        $customer = $this->makeCustomer();

        $config = DatevBookingConfig::forOrganization($this->organization->refresh());
        $result = app(DatevMasterDataExporter::class)->generateDebtors($this->organization, $config);

        $this->assertSame(1, $result['count']);

        $csv = $result['csv'];
        $lines = preg_split('/\r?\n/', trim($csv));
        $this->assertGreaterThanOrEqual(3, count($lines)); // Meta + FieldHeader + 1 Datenzeile

        // Golden-Header: EXTF, Kategorie 16 (Debitoren/Kreditoren), Formatversion 5.
        $meta = mb_convert_encoding($lines[0], 'UTF-8', 'ISO-8859-1');
        $this->assertStringStartsWith('EXTF;700;16;', $meta);
        $this->assertStringContainsString('"Debitoren/Kreditoren";5;', $meta);
        $this->assertStringContainsString('12345', $meta); // Beraternummer
        $this->assertStringContainsString('67890', $meta); // Mandantennummer

        // Datenzeile: volle Spaltenzahl (Formatversion 5) + Kernfelder.
        $header = str_getcsv(mb_convert_encoding($lines[1], 'UTF-8', 'ISO-8859-1'), ';', '"', '\\');
        $data = str_getcsv(mb_convert_encoding($lines[2], 'UTF-8', 'ISO-8859-1'), ';', '"', '\\');
        $this->assertCount(count($header), $data);
        $this->assertSame($config->debtorAccountFor($customer), $data[0]);       // Konto
        $this->assertSame('Muster GmbH', $data[1]);                              // Name Unternehmen
        $this->assertSame('2', $data[6]);                                        // Adressattyp
        $this->assertSame('DE', $data[8]);                                       // EU-Land
        $this->assertSame('123456789', $data[9]);                                // EU-UStID
        $this->assertSame('STR', $data[14]);                                     // Adressart
        $this->assertSame('Hauptstraße 1', $data[15]);                           // Straße
        $this->assertSame('K-1001', $data[98]);                                  // Kunden-/Lief.-Nr. (Pos. 99)
    }

    public function test_explicit_debtor_no_wins_over_number_range(): void {
        $customer = $this->makeCustomer(['debtor_no' => '10077']);

        $config = DatevBookingConfig::forOrganization($this->organization->refresh());
        $result = app(DatevMasterDataExporter::class)->generateDebtors($this->organization, $config);

        $lines = preg_split('/\r?\n/', trim($result['csv']));
        $data = str_getcsv(mb_convert_encoding($lines[2], 'UTF-8', 'ISO-8859-1'), ';', '"', '\\');
        $this->assertSame('10077', $data[0]);
    }

    public function test_export_route_requires_client_numbers_and_downloads(): void {
        $this->makeCustomer();
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        // Ohne Berater-/Mandantennummer → Redirect zur Konfiguration.
        $this->organization->update(['settings' => []]);
        $this->actingAs($admin)->post(route('finance.datev.debtors.export'))
            ->assertRedirect(route('finance.datev.config'));

        // Mit Nummern → CSV-Download + Audit.
        $this->organization->update([
            'settings' => ['datev' => ['advisor_number' => 12345, 'client_number' => 67890]],
        ]);
        $response = $this->actingAs($admin->fresh())->post(route('finance.datev.debtors.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertSame(
            1,
            \App\Models\AuditLog::query()->where('event', 'finance.datev.debtors_exported')->count(),
        );
    }
}
