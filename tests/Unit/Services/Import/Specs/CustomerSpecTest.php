<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Models\Customer;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\CustomerSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class CustomerSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_normalize_handles_decimal_and_boolean_and_country(): void {
        $spec = new CustomerSpec();

        $row = $spec->normalize([
            'name' => '  ACME ',
            'number' => 'K-001',
            'hourly_rate' => '1.234,56',
            'country' => 'de',
            'currency' => 'eur',
            'billable' => 'ja',
        ]);

        $this->assertSame('ACME', $row['name']);
        $this->assertSame('K-001', $row['number']);
        $this->assertSame('1234.56', $row['hourly_rate']);
        $this->assertSame('DE', $row['country']);
        $this->assertSame('EUR', $row['currency']);
        $this->assertTrue($row['billable']);
    }

    public function test_validate_row_collects_required_and_format_issues(): void {
        $spec = new CustomerSpec();

        $row = $spec->normalize([
            'name' => '',
            'email' => 'not-an-email',
            'country' => 'germany',
            'currency' => 'euros',
        ]);

        $issues = $spec->validateRow($row, $this->organization);

        $codes = array_map(static fn($i) => $i->code, $issues);
        $this->assertContains(ImportErrorCode::Required, $codes);
        $this->assertContains(ImportErrorCode::Format, $codes);
    }

    public function test_upsert_creates_and_then_updates_by_number(): void {
        $spec = new CustomerSpec();

        $row = $spec->normalize([
            'name' => 'ACME',
            'number' => 'K-100',
            'currency' => 'EUR',
        ]);

        [$outcome, $issue] = $spec->upsert($row, $this->organization);
        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);
        $this->assertDatabaseHas('customers', [
            'organization_id' => $this->organization->id,
            'number' => 'K-100',
            'name' => 'ACME',
        ]);

        $row2 = $spec->normalize([
            'name' => 'ACME GmbH',
            'number' => 'K-100',
            'currency' => 'EUR',
        ]);
        [$outcome2] = $spec->upsert($row2, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome2);
        $this->assertSame(1, Customer::query()
            ->where('organization_id', $this->organization->id)
            ->where('number', 'K-100')
            ->count());
        $this->assertSame('ACME GmbH', Customer::query()
            ->where('number', 'K-100')->value('name'));
    }

    public function test_upsert_deduplicates_by_vat_id_when_number_differs(): void {
        $spec = new CustomerSpec();

        // Bestandskunde mit USt-IdNr. und Nummer K-1.
        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Bestand AG',
            'number' => 'K-1',
            'vat_id' => 'DE999888777',
        ]);

        // Reimport mit ABWEICHENDER Nummer, aber gleicher USt-IdNr. → Dedup statt Dublette.
        $row = $spec->normalize([
            'name' => 'Bestand Aktiengesellschaft',
            'number' => 'K-2',
            'vat_id' => 'DE999888777',
            'currency' => 'EUR',
        ]);
        [$outcome] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $this->assertSame(1, Customer::query()
            ->where('organization_id', $this->organization->id)
            ->where('vat_id', 'DE999888777')
            ->count(), 'Keine Dublette angelegt');
        // Bestehende Nummer bleibt erhalten (CSV-Nummer überschreibt sie nicht).
        $this->assertSame('K-1', Customer::query()->where('vat_id', 'DE999888777')->value('number'));
        $this->assertSame('Bestand Aktiengesellschaft', Customer::query()->where('vat_id', 'DE999888777')->value('name'));
    }

    public function test_upsert_or_stage_routes_unmatched_to_inbox(): void {
        $spec = new CustomerSpec();

        [$outcome] = $spec->upsertOrStage(
            $spec->normalize(['name' => 'Niemand AG', 'vat_id' => 'DE000111222', 'currency' => 'EUR']),
            $this->organization,
        );

        $this->assertSame(ImportOutcome::Skipped, $outcome);
        $this->assertSame(0, Customer::query()->where('name', 'Niemand AG')->count(), 'Nicht blind angelegt');
        $this->assertDatabaseHas('integration_inbox_items', [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'csv-import',
            'target_type' => (new Customer)->getMorphClass(),
            'case_type' => 'unmatched',
            'status' => 'open',
        ]);
    }

    public function test_upsert_or_stage_updates_existing_match(): void {
        $spec = new CustomerSpec();
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'vat_id' => 'DE555444333',
            'name' => 'Alt',
        ]);

        [$outcome] = $spec->upsertOrStage(
            $spec->normalize(['name' => 'Neu', 'vat_id' => 'DE555444333', 'currency' => 'EUR']),
            $this->organization,
        );

        $this->assertSame(ImportOutcome::Updated, $outcome);
        $this->assertSame('Neu', $customer->fresh()->name);
        $this->assertSame(0, \App\Models\IntegrationInboxItem::query()->count(), 'Kein Inbox-Eintrag bei Treffer');
    }

    public function test_external_id_binds_and_reimport_is_stable(): void {
        $spec = new CustomerSpec();

        // Erstimport mit Fremd-ID → Datensatz + ExternalReference-Bindung.
        [$o1] = $spec->upsert($spec->normalize([
            'name' => 'Quelle GmbH', 'number' => 'K-1', 'external_id' => 'EXT-9', 'currency' => 'EUR',
        ]), $this->organization);
        $this->assertSame(ImportOutcome::Created, $o1);

        $customer = Customer::query()->where('name', 'Quelle GmbH')->first();
        $this->assertNotNull($customer);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'csv-import',
            'external_type' => 'customers',
            'external_id' => 'EXT-9',
            'referenceable_id' => $customer->id,
        ]);

        // Reimport mit ABWEICHENDER Nummer + Name, gleiche Fremd-ID → selber Datensatz.
        [$o2] = $spec->upsert($spec->normalize([
            'name' => 'Quelle GmbH umbenannt', 'number' => 'K-2', 'external_id' => 'EXT-9', 'currency' => 'EUR',
        ]), $this->organization);
        $this->assertSame(ImportOutcome::Updated, $o2);
        $this->assertSame(1, Customer::query()->where('organization_id', $this->organization->id)->count(), 'Keine Dublette trotz neuer Nummer');
        $this->assertSame('Quelle GmbH umbenannt', $customer->fresh()->name);
    }
}
