<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Models\User;
use App\Services\Export\Specs\UserExportSpec;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\UserSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class UserSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_normalize_handles_personnel_number_and_email(): void {
        $spec = new UserSpec();

        $row = $spec->normalize([
            'name' => '  Max Muster ',
            'personnel_number' => ' P-1001 ',
            'email' => '  MAX@Example.COM ',
        ]);

        $this->assertSame('Max Muster', $row['name']);
        $this->assertSame('P-1001', $row['personnel_number']);
        $this->assertSame('max@example.com', $row['email']);
    }

    public function test_upsert_creates_and_updates_personnel_number_by_email(): void {
        $spec = new UserSpec();

        $row = $spec->normalize([
            'name' => 'Max Muster',
            'personnel_number' => 'P-1001',
            'email' => 'max@example.test',
        ]);

        [$outcome, $issue] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);
        $this->assertDatabaseHas('users', [
            'organization_id' => $this->organization->id,
            'email' => 'max@example.test',
            'personnel_number' => 'P-1001',
        ]);

        $row2 = $spec->normalize([
            'name' => 'Max Muster',
            'personnel_number' => 'P-2002',
            'email' => 'max@example.test',
        ]);

        [$outcome2] = $spec->upsert($row2, $this->organization);

        $this->assertSame(ImportOutcome::Updated, $outcome2);
        $this->assertSame('P-2002', User::query()
            ->where('email', 'max@example.test')
            ->value('personnel_number'));
    }

    public function test_export_includes_personnel_number_and_can_filter_by_it(): void {
        $spec = new UserExportSpec();
        User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Max Muster',
            'email' => 'max@example.test',
            'personnel_number' => 'P-1001',
        ]);
        User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Erika Muster',
            'email' => 'erika@example.test',
            'personnel_number' => 'P-2002',
        ]);

        $rows = collect($spec->query($this->organization, ['q' => 'P-1001']))
            ->map(fn(User $user): array => $spec->toRow($user))
            ->all();

        $this->assertSame(['name', 'personnel_number', 'email', 'hourly_rate', 'internal_rate', 'home_address'], $spec->columns());
        $this->assertCount(1, $rows);
        $this->assertSame('P-1001', $rows[0]['personnel_number']);
        $this->assertSame('max@example.test', $rows[0]['email']);
    }
}
