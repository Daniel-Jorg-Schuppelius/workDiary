<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import\Specs;

use App\Enums\Import\ImportErrorCode;
use App\Models\User;
use App\Services\Import\ImportOutcome;
use App\Services\Import\Specs\ScheduledShiftSpec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class ScheduledShiftSpecTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_normalize_parses_german_date_and_time(): void {
        $spec = new ScheduledShiftSpec();

        $row = $spec->normalize([
            'user_email' => '  Max@Example.COM ',
            'date' => '28.05.2026',
            'start_time' => '9:05',
            'end_time' => '17:30',
            'status' => 'Published',
        ]);

        $this->assertSame('max@example.com', $row['user_email']);
        $this->assertSame('2026-05-28', $row['date']);
        $this->assertSame('09:05', $row['start_time']);
        $this->assertSame('17:30', $row['end_time']);
        $this->assertSame('published', $row['status']);
    }

    public function test_validate_row_flags_required_and_format_issues(): void {
        $spec = new ScheduledShiftSpec();

        $row = $spec->normalize([
            'user_email' => 'not-an-email',
            'date' => 'kein-datum',
            'status' => 'bogus',
        ]);

        $issues = $spec->validateRow($row, $this->organization);
        $codes = array_map(static fn($i) => $i->code, $issues);

        $this->assertContains(ImportErrorCode::Format, $codes);
        $this->assertContains(ImportErrorCode::Required, $codes);
    }

    public function test_upsert_fails_when_user_missing(): void {
        $spec = new ScheduledShiftSpec();

        $row = $spec->normalize([
            'user_email' => 'ghost@example.com',
            'date' => '2026-05-28',
        ]);

        [$outcome, $issue] = $spec->upsert($row, $this->organization);

        $this->assertSame(ImportOutcome::Failed, $outcome);
        $this->assertSame(ImportErrorCode::FkMissing, $issue->code);
    }

    public function test_upsert_creates_then_updates_by_user_and_date(): void {
        $spec = new ScheduledShiftSpec();
        User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'email' => 'worker@example.com',
        ]);

        $row = $spec->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-05-28',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'status' => 'published',
        ]);

        [$outcome, $issue] = $spec->upsert($row, $this->organization);
        $this->assertSame(ImportOutcome::Created, $outcome);
        $this->assertNull($issue);
        $this->assertDatabaseHas('scheduled_shifts', [
            'organization_id' => $this->organization->id,
            'date' => '2026-05-28',
            'status' => 'published',
        ]);

        $row2 = $spec->normalize([
            'user_email' => 'worker@example.com',
            'date' => '2026-05-28',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'confirmed',
        ]);

        [$outcome2] = $spec->upsert($row2, $this->organization);
        $this->assertSame(ImportOutcome::Updated, $outcome2);
        $this->assertSame(1, \App\Models\ScheduledShift::query()
            ->where('organization_id', $this->organization->id)
            ->count());
    }
}
