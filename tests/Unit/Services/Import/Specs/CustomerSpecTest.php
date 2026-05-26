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
}
