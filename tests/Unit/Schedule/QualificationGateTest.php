<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : QualificationGateTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Schedule;

use App\Models\{Qualification, ScheduledShift, ShiftType, User};
use App\Services\Schedule\QualificationGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 013 — Sperrhinweis bei fehlender Pflichtqualifikation:
 * {@see QualificationGate::missingFor()} liefert die für den Schichttyp
 * geforderten, vom zugewiesenen Mitarbeitenden NICHT gehaltenen Qualifikationen.
 */
class QualificationGateTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function shift(User $user, ShiftType $type): ScheduledShift {
        return ScheduledShift::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'shift_type_id' => $type->id,
            'date' => '2030-05-04',
        ]);
    }

    public function test_reports_missing_required_qualification(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Höhenarbeit', 'abbreviation' => 'HA']);
        $type->qualifications()->attach($qual->id);

        $missing = (new QualificationGate())->missingFor($this->shift($user, $type));

        $this->assertCount(1, $missing);
        $this->assertSame('Höhenarbeit', $missing->first()?->name);
    }

    public function test_no_gap_when_user_holds_qualification(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $type->qualifications()->attach($qual->id);
        $user->qualifications()->attach($qual->id);

        $this->assertTrue((new QualificationGate())->missingFor($this->shift($user, $type))->isEmpty());
    }

    public function test_expired_qualification_counts_as_missing(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $type->qualifications()->attach($qual->id);
        // Gültigkeit endete vor dem Schichttag (2030-05-04) → abgelaufen.
        $user->qualifications()->attach($qual->id, ['valid_from' => '2028-01-01', 'valid_until' => '2029-12-31']);

        $this->assertCount(1, (new QualificationGate())->missingFor($this->shift($user, $type)));
    }

    public function test_not_yet_valid_qualification_counts_as_missing(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $type->qualifications()->attach($qual->id);
        // Gültigkeit beginnt erst nach dem Schichttag → noch nicht gültig.
        $user->qualifications()->attach($qual->id, ['valid_from' => '2031-01-01', 'valid_until' => null]);

        $this->assertCount(1, (new QualificationGate())->missingFor($this->shift($user, $type)));
    }

    public function test_valid_window_covering_shift_day_is_not_missing(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);
        $qual = Qualification::factory()->create(['organization_id' => $this->organization->id]);
        $type->qualifications()->attach($qual->id);
        $user->qualifications()->attach($qual->id, ['valid_from' => '2030-01-01', 'valid_until' => '2030-12-31']);

        $this->assertTrue((new QualificationGate())->missingFor($this->shift($user, $type))->isEmpty());
    }

    public function test_no_gap_when_shift_type_has_no_requirement(): void {
        $this->setUpOrganization();
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $type = ShiftType::factory()->create(['organization_id' => $this->organization->id]);

        $this->assertTrue((new QualificationGate())->missingFor($this->shift($user, $type))->isEmpty());
    }
}
