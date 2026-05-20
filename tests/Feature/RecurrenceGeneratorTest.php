<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecurrenceGeneratorTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Diary\Mode;
use App\Enums\Diary\Status as DiaryStatus;
use App\Models\DiaryEntry;
use App\Enums\Diary\LocationMode;
use App\Models\Project;
use App\Models\RecurrenceRule;
use App\Models\User;
use App\Services\Recurrence\RecurrenceGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;
use App\Enums\Project\ProjectStatus;
use App\Enums\Recurrence\RecurrenceFrequency;

class RecurrenceGeneratorTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Project $project;

    private CarbonImmutable $now;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'name' => 'Wartung ' . uniqid('', true),
            'status' => ProjectStatus::Active->value,
            'is_maintenance' => true,
        ]);
        $this->now = CarbonImmutable::create(2026, 5, 19);
    }

    public function test_weekly_rule_generates_entries_for_each_matching_day(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Weekly->value,
            'interval' => 1,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
        ]);

        $created = app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 28);

        $this->assertSame(7, $created);
        $entries = DiaryEntry::where('recurrence_rule_id', $rule->id)->orderBy('due_date')->get();
        $this->assertSame('2026-05-04', $entries->first()->due_date->toDateString());
        $this->assertSame('2026-06-15', $entries->last()->due_date->toDateString());
        $entries->each(function (DiaryEntry $entry): void {
            $this->assertSame(Mode::Recurring, $entry->mode);
            $this->assertSame(DiaryStatus::Open, $entry->status);
        });
    }

    public function test_second_run_is_idempotent_and_creates_nothing(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Weekly->value,
            'byweekday' => 'WE',
            'starts_on' => '2026-05-06',
        ]);

        $first = app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 28);
        $second = app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 28);

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
    }

    public function test_monthly_rule_with_bymonthday_clamps_at_month_end(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Monthly->value,
            'interval' => 1,
            'bymonthday' => 31,
            'starts_on' => '2026-01-31',
        ]);

        $created = app(RecurrenceGenerator::class)
            ->generateForRule($rule->fresh(), CarbonImmutable::create(2026, 3, 1), 90);

        $this->assertGreaterThanOrEqual(2, $created);
        $entries = DiaryEntry::where('recurrence_rule_id', $rule->id)->orderBy('due_date')->get();
        $february = $entries->first(fn(DiaryEntry $e) => $e->due_date->format('Y-m') === '2026-02');
        $this->assertNotNull($february, 'February occurrence must exist.');
        $this->assertSame('2026-02-28', $february->due_date->toDateString());
    }

    public function test_rule_respects_ends_on_boundary(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Weekly->value,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
            'ends_on' => '2026-05-25',
        ]);

        $created = app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 90);

        $this->assertSame(4, $created); // 04, 11, 18, 25
        $entries = DiaryEntry::where('recurrence_rule_id', $rule->id)->orderBy('due_date')->get();
        $this->assertSame('2026-05-25', $entries->last()->due_date->toDateString());
    }

    public function test_inactive_rules_are_skipped_by_generate_all(): void {
        $this->makeRule([
            'frequency' => RecurrenceFrequency::Daily->value,
            'starts_on' => '2026-05-15',
            'is_active' => false,
        ]);

        $created = app(RecurrenceGenerator::class)->generateAll(7, $this->now);

        $this->assertSame(0, $created);
    }

    public function test_template_placeholders_are_rendered_into_diary_entry(): void {
        $rule = $this->makeRule([
            'frequency' => RecurrenceFrequency::Weekly->value,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
            'title_template' => 'Check KW {week}',
            'content_template' => 'Routine am {date}',
        ]);

        app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 7);

        $entry = DiaryEntry::where('recurrence_rule_id', $rule->id)->orderBy('due_date')->first();
        $this->assertNotNull($entry);
        $this->assertSame('Check KW 19', $entry->title);
        $this->assertSame('Routine am 04.05.2026', $entry->content);
    }

    public function test_rule_without_user_throws(): void {
        $rule = RecurrenceRule::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'name' => 'Bad rule',
            'content_template' => 'x',
            'default_location_mode' => LocationMode::Onsite->value,
            'frequency' => RecurrenceFrequency::Weekly->value,
            'interval' => 1,
            'byweekday' => 'MO',
            'starts_on' => '2026-05-04',
            'is_active' => true,
            'created_by' => null,
            'assigned_user_id' => null,
        ]);

        $this->expectException(\LogicException::class);
        app(RecurrenceGenerator::class)->generateForRule($rule->fresh(), $this->now, 14);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRule(array $overrides = []): RecurrenceRule {
        return RecurrenceRule::create(array_merge([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'name' => 'Test ' . uniqid('', true),
            'content_template' => 'Wartungs-Auftrag',
            'default_location_mode' => LocationMode::Onsite->value,
            'frequency' => RecurrenceFrequency::Weekly->value,
            'interval' => 1,
            'starts_on' => '2026-05-04',
            'is_active' => true,
            'created_by' => $this->user->id,
        ], $overrides));
    }
}
