<?php
/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectRecurrenceRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\LocationMode;
use App\Enums\Project\ProjectStatus;
use App\Enums\Recurrence\RecurrenceFrequency;
use App\Models\{DiaryEntry, Project, RecurrenceRule, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRecurrenceRuleTest extends TestCase {
    use RefreshDatabase;

    private User $owner;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();

        $this->owner = User::factory()->user()->create();
        $this->project = Project::create([
            'organization_id' => $this->owner->organization_id,
            'name' => 'Wartung ' . uniqid('', true),
            'status' => ProjectStatus::Active->value,
            'is_maintenance' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_owner_can_store_recurrence_rule(): void {
        $this->actingAs($this->owner)
            ->post(route('projects.recurrence-rules.store', $this->project), [
                'name' => 'DATEV monatlich',
                'content_template' => 'Update am {date}',
                'default_location_mode' => LocationMode::Remote->value,
                'frequency' => RecurrenceFrequency::Monthly->value,
                'interval' => 1,
                'bymonthday' => 15,
                'starts_on' => '2026-06-15',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('recurrence_rules', [
            'project_id' => $this->project->id,
            'name' => 'DATEV monatlich',
            'frequency' => RecurrenceFrequency::Monthly->value,
            'bymonthday' => 15,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_owner_can_update_recurrence_rule(): void {
        $rule = $this->makeRule();

        $this->actingAs($this->owner)
            ->put(route('projects.recurrence-rules.update', [$this->project, $rule]), [
                'name' => 'Umbenannt',
                'content_template' => $rule->content_template,
                'default_location_mode' => LocationMode::Onsite->value,
                'frequency' => $rule->frequency->value,
                'interval' => 2,
                'byweekday' => 'TU',
                'starts_on' => $rule->starts_on->toDateString(),
            ])
            ->assertRedirect();

        $rule->refresh();
        $this->assertSame('Umbenannt', $rule->name);
        $this->assertSame(2, $rule->interval);
        $this->assertSame('TU', $rule->byweekday);
    }

    public function test_owner_can_delete_recurrence_rule(): void {
        $rule = $this->makeRule();

        $this->actingAs($this->owner)
            ->delete(route('projects.recurrence-rules.destroy', [$this->project, $rule]))
            ->assertRedirect();

        $this->assertDatabaseMissing('recurrence_rules', ['id' => $rule->id]);
    }

    public function test_run_generates_diary_entries(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Weekly->value,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
        ]);

        $this->actingAs($this->owner)
            ->post(route('projects.recurrence-rules.run', [$this->project, $rule]))
            ->assertRedirect();

        $count = DiaryEntry::query()->where('recurrence_rule_id', $rule->id)->count();
        $this->assertGreaterThan(0, $count);
    }

    public function test_foreign_user_cannot_modify_rule(): void {
        $rule = $this->makeRule();
        // Stranger gehört zur SELBEN Org – der Tenant-Scope schiebt Cross-
        // Org-Zugriffe bereits vorher mit 404 ab. Hier prüfen wir die
        // Ownership-Schicht innerhalb derselben Organisation.
        $stranger = User::factory()->user()->create([
            'organization_id' => $this->owner->organization_id,
        ]);

        $this->actingAs($stranger)
            ->put(route('projects.recurrence-rules.update', [$this->project, $rule]), [
                'name' => 'Hijacked',
                'content_template' => 'x',
                'default_location_mode' => LocationMode::Onsite->value,
                'frequency' => RecurrenceFrequency::Daily->value,
                'interval' => 1,
                'starts_on' => '2026-05-01',
            ])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRule(array $overrides = []): RecurrenceRule {
        return RecurrenceRule::create(array_merge([
            'organization_id' => $this->owner->organization_id,
            'project_id' => $this->project->id,
            'name' => 'Wöchentliche Routine',
            'content_template' => 'Routine am {date}',
            'default_location_mode' => LocationMode::Onsite->value,
            'frequency' => RecurrenceFrequency::Weekly->value,
            'interval' => 1,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
            'is_active' => true,
            'created_by' => $this->owner->id,
        ], $overrides));
    }
}
