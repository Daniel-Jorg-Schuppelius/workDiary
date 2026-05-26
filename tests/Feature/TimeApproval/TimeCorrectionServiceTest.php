<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\TimeApproval;

use App\Enums\TimeApproval\{MonthClosureStatus, TimeCorrectionStatus};
use App\Models\{MonthClosure, Project, TimeEntry, User};
use App\Services\TimeApproval\{TimeCorrectionService, TimeCorrectionWorkflowException};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TimeCorrectionServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private TimeCorrectionService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->service = app(TimeCorrectionService::class);
    }

    public function test_create_draft_persists_request_with_items(): void {
        $user = $this->makeUser();
        $entry = $this->makeTimeEntry($user);

        $request = $this->service->createDraft(
            $user,
            CarbonImmutable::parse((string) $entry->date?->format('Y-m-d')),
            str_repeat('A', 25),
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $entry->id,
                'action' => 'update',
                'before' => ['minutes' => 60],
                'after' => ['minutes' => 90],
            ]],
            $user,
        );

        $this->assertSame(TimeCorrectionStatus::Draft, $request->status);
        $this->assertCount(1, $request->items);
        $this->assertDatabaseHas('time_correction_items', [
            'time_correction_request_id' => $request->id,
            'target_type' => TimeEntry::class,
        ]);
    }

    public function test_create_draft_requires_items_and_reason(): void {
        $user = $this->makeUser();
        $this->actingAs($user);

        try {
            $this->service->createDraft($user, CarbonImmutable::parse('2024-01-15'), str_repeat('x', 30), []);
            $this->fail('Erwartete noItems-Exception.');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('noItems', $e->reasonCode);
        }

        try {
            $this->service->createDraft($user, CarbonImmutable::parse('2024-01-15'), 'kurz', [[
                'target_type' => TimeEntry::class,
                'target_id' => 1,
                'action' => 'update',
            ]]);
            $this->fail('Erwartete reasonTooShort.');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('reasonTooShort', $e->reasonCode);
        }
    }

    public function test_apply_updates_target_idempotently(): void {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $entry = $this->makeTimeEntry($user, ['minutes' => 60]);

        $request = $this->service->createDraft(
            $user,
            CarbonImmutable::parse((string) $entry->date?->format('Y-m-d')),
            str_repeat('A', 30),
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $entry->id,
                'action' => 'update',
                'before' => ['minutes' => 60],
                'after' => ['minutes' => 120],
            ]],
            $user,
        );
        $request = $this->service->submit($request, $user);
        $request = $this->service->approve($request, $admin);

        $applied = $this->service->apply($request);
        $this->assertSame(TimeCorrectionStatus::Applied, $applied->status);
        $this->assertNotNull($applied->applied_at);
        $this->assertSame(120, (int) $entry->fresh()?->minutes);

        // Idempotenz
        $again = $this->service->apply($applied);
        $this->assertSame($applied->id, $again->id);
        $this->assertSame(TimeCorrectionStatus::Applied, $again->status);
        $this->assertSame(120, (int) $entry->fresh()?->minutes);
    }

    public function test_apply_blocked_when_month_is_locked(): void {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $entry = $this->makeTimeEntry($user, ['date' => '2024-01-15']);

        // Monat zwangsgesperrt: direkt einen Closure im Status "locked" anlegen.
        MonthClosure::query()->create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'period_year' => 2024,
            'period_month' => 1,
            'status' => MonthClosureStatus::Locked,
            'days_total' => 31,
            'days_with_attendance' => 0,
            'days_closed' => 0,
            'days_open' => 0,
            'warnings_count' => 0,
            'locked_at' => CarbonImmutable::now(),
        ]);

        $request = $this->service->createDraft(
            $user,
            CarbonImmutable::parse('2024-01-15'),
            str_repeat('A', 30),
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $entry->id,
                'action' => 'update',
                'after' => ['minutes' => 30],
            ]],
            $user,
        );
        $request = $this->service->approve($this->service->submit($request, $user), $admin);

        $this->expectException(TimeCorrectionWorkflowException::class);
        $this->expectExceptionMessageMatches('/gesperrten Monat/u');
        $this->service->apply($request);
    }

    public function test_withdraw_only_by_requester(): void {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $entry = $this->makeTimeEntry($user);

        $request = $this->service->createDraft(
            $user,
            CarbonImmutable::parse((string) $entry->date?->format('Y-m-d')),
            str_repeat('A', 25),
            [[
                'target_type' => TimeEntry::class,
                'target_id' => $entry->id,
                'action' => 'update',
                'after' => ['minutes' => 30],
            ]],
            $user,
        );
        $request = $this->service->submit($request, $user);

        try {
            $this->service->withdraw($request, $other);
            $this->fail('Erwartete notRequester-Exception.');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('notRequester', $e->reasonCode);
        }

        $withdrawn = $this->service->withdraw($request, $user);
        $this->assertSame(TimeCorrectionStatus::Withdrawn, $withdrawn->status);
    }

    public function test_reject_requires_long_reason(): void {
        $user = $this->makeUser();
        $admin = $this->makeUser();
        $entry = $this->makeTimeEntry($user);

        $request = $this->service->submit(
            $this->service->createDraft(
                $user,
                CarbonImmutable::parse((string) $entry->date?->format('Y-m-d')),
                str_repeat('A', 25),
                [[
                    'target_type' => TimeEntry::class,
                    'target_id' => $entry->id,
                    'action' => 'update',
                    'after' => ['minutes' => 30],
                ]],
                $user,
            ),
            $user,
        );

        try {
            $this->service->reject($request, $admin, 'nope');
            $this->fail('Erwartete reasonTooShort.');
        } catch (TimeCorrectionWorkflowException $e) {
            $this->assertSame('reasonTooShort', $e->reasonCode);
        }

        $rejected = $this->service->reject($request, $admin, str_repeat('Z', 30));
        $this->assertSame(TimeCorrectionStatus::Rejected, $rejected->status);
    }

    private function makeUser(): User {
        /** @var User $user */
        $user = User::factory()->create(['organization_id' => $this->organization->id]);

        return $user;
    }

    /** @param  array<string, mixed>  $attrs */
    private function makeTimeEntry(User $user, array $attrs = []): TimeEntry {
        /** @var Project $project */
        $project = Project::factory()->create(['organization_id' => $this->organization->id]);
        /** @var TimeEntry $entry */
        $entry = TimeEntry::factory()->create(array_merge([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'project_id' => $project->id,
            'date' => '2024-03-10',
            'minutes' => 60,
        ], $attrs));

        return $entry;
    }
}
