<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierSpecTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Models\Supplier\Supplier;
use App\Services\Import\ImportOutcome;
use App\Services\Supplier\Import\SupplierSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class SupplierSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_upsert_creates_and_then_updates_by_number(): void {
        $spec = new SupplierSpec();

        $row = $spec->normalize(['name' => 'Großhandel', 'number' => 'L-100', 'currency' => 'EUR']);
        [$outcome, $issue] = $spec->upsert($row, $this->organization);
        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);

        $row2 = $spec->normalize(['name' => 'Großhandel GmbH', 'number' => 'L-100', 'currency' => 'EUR']);
        [$outcome2] = $spec->upsert($row2, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome2);
        $this->assertSame(1, Supplier::query()
            ->where('organization_id', $this->organization->id)
            ->where('number', 'L-100')->count());
        $this->assertSame('Großhandel GmbH', Supplier::query()->where('number', 'L-100')->value('name'));
    }

    public function test_upsert_deduplicates_by_vat_id_when_number_differs(): void {
        $spec = new SupplierSpec();

        Supplier::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Lieferant AG',
            'number' => 'L-1',
            'vat_id' => 'DE111222333',
        ]);

        $row = $spec->normalize([
            'name' => 'Lieferant Aktiengesellschaft',
            'number' => 'L-2',
            'vat_id' => 'DE111222333',
            'currency' => 'EUR',
        ]);
        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $this->assertSame(1, Supplier::query()
            ->where('organization_id', $this->organization->id)
            ->where('vat_id', 'DE111222333')->count(), 'Keine Dublette angelegt');
        $this->assertSame('L-1', Supplier::query()->where('vat_id', 'DE111222333')->value('number'));
    }

    /** Konsolidierungs-Audit 2026-10-23 (A2): die Roh-Regex ließ jede Buchstabenfolge als Code durch. */
    public function test_validate_row_accepts_only_iso_country_and_currency_codes(): void {
        $spec = new SupplierSpec();
        $formatFields = fn(array $row) => array_values(array_map(
            static fn($i) => $i->field,
            array_filter($spec->validateRow($spec->normalize(['name' => 'Großhandel'] + $row), $this->organization), static fn($i) => $i->code === ImportErrorCode::Format),
        ));

        $this->assertSame(['country', 'currency'], $formatFields(['country' => 'QQ', 'currency' => 'ABC']));
        $this->assertSame([], $formatFields(['country' => 'nl', 'currency' => 'usd']));
    }
}
