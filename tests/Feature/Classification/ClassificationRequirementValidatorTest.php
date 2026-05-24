<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClassificationRequirementValidatorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};
use App\Models\{AuditLog, ClassificationRequirement, DiaryEntry, EntryType, Organization, User};
use App\Services\Classification\ClassificationRequirementValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationRequirementValidatorTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private DiaryEntry $entry;

    private ClassificationRequirementValidator $validator;

    protected function setUp(): void {
        parent::setUp();

        $this->org = Organization::factory()->create();

        $entryType = EntryType::factory()
            ->service()
            ->create(['organization_id' => $this->org->id]);

        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->actingAs($this->actor);

        $this->entry = DiaryEntry::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->actor->id,
            'entry_type_id' => $entryType->id,
            'priority' => 'normal',
        ]);

        $this->validator = new ClassificationRequirementValidator;
    }

    public function test_hard_missing_requirement_on_phase_returns_blocking_result(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);

        $results = $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::BeforeComplete,
            ['entry_type' => ['service']]
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isBlocking());
        $this->assertSame('defect_type', $results[0]->requiredDomain);
    }

    public function test_soft_missing_requirement_returns_non_blocking_result(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ]);

        $results = $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::BeforeComplete,
            ['entry_type' => ['service']]
        );

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->isBlocking());
    }

    public function test_only_if_json_condition_mismatch_skips_requirement(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'only_if_json' => ['priority' => ['high', 'critical']],
        ]);

        $results = $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::BeforeComplete,
            [
                'entry_type' => ['service'],
                'priority' => ['normal'],
            ]
        );

        $this->assertSame([], $results);
    }

    public function test_only_if_json_condition_match_enforces_requirement(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'only_if_json' => ['priority' => ['high', 'critical']],
        ]);

        $results = $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::BeforeComplete,
            [
                'entry_type' => ['service'],
                'priority' => ['critical'],
            ]
        );

        $this->assertCount(1, $results);
    }

    public function test_max_count_violation_is_reported(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'max_count' => 1,
        ]);

        $results = $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::OnCreate,
            [
                'entry_type' => ['service'],
                'activity' => ['analysis', 'repair'],
            ]
        );

        $this->assertCount(1, $results);
        $this->assertSame(2, $results[0]->actualCount);
        $this->assertSame(1, $results[0]->maxCount);
    }

    public function test_hard_requirement_logs_a_system_audit_event(): void {
        ClassificationRequirement::factory()->create([
            'organization_id' => $this->org->id,
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeSign->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ]);

        $this->validator->validate(
            $this->entry,
            ClassificationRequirementPhase::BeforeSign,
            ['entry_type' => ['service']]
        );

        $auditCount = AuditLog::query()
            ->where('event', 'classification.requirementMissing')
            ->count();

        $this->assertSame(1, $auditCount);
    }
}
