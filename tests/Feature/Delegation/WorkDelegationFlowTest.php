<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkDelegationFlowTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Enums\Delegation\WorkDelegationKind;
use App\Enums\User\UserRole;
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\Absence\{SickLeave, Vacation};
use App\Models\Delegation\WorkDelegation;
use App\Models\Platform\User;
use App\Services\Delegation\Demo\DelegationDemoBlock;
use App\Services\Delegation\WorkDelegationService;
use App\Services\Demo\Contracts\DemoSeedContext;
use App\Support\Sqid;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Oberfläche der Vertretung (Feature 202, MVP-1647): Dialog aus Urlaub,
 * Krankmeldung oder frei, Rechte, Beenden, Kopfhinweis, Demo-Block.
 */
final class WorkDelegationFlowTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        Carbon::setTestNow('2026-11-12 10:00:00');
        $this->setUpOrganization();
    }

    /** @param  array<string, mixed>  $attributes */
    private function worker(array $attributes = []): User {
        return $this->userWithRole(UserRole::User->value, attributes: $attributes);
    }

    private function lead(): User {
        return $this->userWithRole(UserRole::Teamleitung->value);
    }

    private function sqid(User $user): string {
        return Sqid::encode(User::class, (int) $user->id);
    }

    private function vacation(User $owner, VacationStatus $status = VacationStatus::Approved): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $owner->id,
            'start_date' => '2026-11-16',
            'end_date' => '2026-11-27',
            'type' => VacationType::Vacation->value,
            'status' => $status->value,
        ]);
    }

    public function test_the_dialog_from_an_approved_vacation_suggests_the_named_deputy(): void {
        $deputy = $this->worker(['name' => 'Ben Muster']);
        $anna = $this->worker(['name' => 'Anna Beispiel', 'deputy_user_id' => $deputy->id]);
        $vacation = $this->vacation($anna);

        $response = $this->actingAs($anna)->get(route('delegations.create', ['vacation' => $vacation->sqid, 'dialog' => 1]));
        $response->assertOk()
            ->assertSee('value="' . $this->sqid($deputy) . '" selected', false)
            ->assertSee('value="2026-11-16"', false)
            ->assertSee('value="2026-11-27"', false)
            ->assertSee('name="absence" value="vacations:' . $vacation->sqid . '"', false);

        $this->actingAs($anna)->post(route('delegations.store'), [
            'represented_user_id' => $this->sqid($anna),
            'deputy_user_id' => $this->sqid($deputy),
            'valid_from' => '2026-11-16',
            'valid_until' => '2026-11-27',
            'kind' => WorkDelegationKind::Vacation->value,
            'absence' => 'vacations:' . $vacation->sqid,
        ])->assertRedirect(route('delegations.index'));

        $delegation = WorkDelegation::query()->sole();
        $this->assertSame((int) $deputy->id, $delegation->deputy_user_id);
        $this->assertSame((int) $vacation->id, $delegation->absence_id);
        $this->assertSame((int) $anna->id, $delegation->created_by);

        // Nicht genehmigter Urlaub taugt nicht als Anlass.
        $requester = $this->worker();
        $pending = $this->vacation($requester, VacationStatus::Pending);
        $this->actingAs($requester)->get(route('delegations.create', ['vacation' => $pending->sqid]))->assertNotFound();
    }

    public function test_only_management_creates_a_delegation_for_someone_else(): void {
        $anna = $this->worker();
        $ben = $this->worker();
        $clara = $this->worker();
        $payload = [
            'represented_user_id' => $this->sqid($anna),
            'deputy_user_id' => $this->sqid($ben),
            'valid_from' => '2026-11-12',
            'kind' => WorkDelegationKind::Other->value,
        ];

        $this->actingAs($clara)->post(route('delegations.store'), $payload)->assertForbidden();
        $this->actingAs($clara)->get(route('delegations.create', ['user' => $this->sqid($anna)]))->assertForbidden();
        $this->actingAs($clara)->get(route('delegations.create', ['vacation' => $this->vacation($anna)->sqid]))->assertForbidden();

        $lead = $this->lead();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->assertTrue($lead->can('user.delegation.manage'));
        $this->actingAs($lead)->post(route('delegations.store'), $payload)->assertRedirect(route('delegations.index'));
        $this->assertSame(1, WorkDelegation::query()->count());
    }

    public function test_from_a_sick_leave_only_management_with_sick_leave_access(): void {
        $anna = $this->worker();
        $sick = SickLeave::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $anna->id, 'start_date' => '2026-11-12', 'end_date' => '2026-11-18']);

        $this->actingAs($anna)->get(route('delegations.create', ['sick_leave' => $sick->sqid]))->assertForbidden();
        $this->actingAs($this->worker())->get(route('delegations.create', ['sick_leave' => $sick->sqid]))->assertForbidden();

        $lead = $this->lead();
        $this->actingAs($lead)->get(route('delegations.create', ['sick_leave' => $sick->sqid, 'dialog' => 1]))
            ->assertOk()
            ->assertSee('name="absence" value="sick_leaves:' . $sick->sqid . '"', false)
            ->assertSee('value="2026-11-18"', false);

        $this->actingAs($lead)->post(route('delegations.store'), [
            'represented_user_id' => $this->sqid($anna),
            'deputy_user_id' => $this->sqid($this->worker()),
            'valid_from' => '2026-11-12',
            'valid_until' => '2026-11-18',
            'kind' => WorkDelegationKind::Sickness->value,
            'absence' => 'sick_leaves:' . $sick->sqid,
        ])->assertRedirect();
        $this->assertSame(WorkDelegationKind::Sickness, WorkDelegation::query()->sole()->kind);
    }

    public function test_list_and_banner_show_the_delegation_without_the_reason_to_the_deputy(): void {
        $anna = $this->worker(['name' => 'Anna Beispiel']);
        $ben = $this->worker(['name' => 'Ben Muster']);
        $outsider = $this->worker(['name' => 'Clara Außen']);
        app(WorkDelegationService::class)->create($anna, $ben, CarbonImmutable::parse('2026-11-12'), CarbonImmutable::parse('2026-11-20'), WorkDelegationKind::Sickness, null, 'Reha-Aufenthalt', $anna);

        $this->actingAs($ben)->get(route('delegations.index'))
            ->assertOk()
            ->assertSee('Anna Beispiel')
            ->assertSee(__('delegation.banner.represents', ['names' => __('delegation.banner.person_until', ['name' => 'Anna Beispiel', 'date' => '20.11.2026'])]))
            // Anlass und Notiz sieht die Vertretung nicht („Krankheit“ steht ohnehin in der Navigation).
            ->assertDontSee('data-delegation-reason', false)
            ->assertDontSee('Reha-Aufenthalt');

        $this->actingAs($anna)->get(route('delegations.index'))
            ->assertOk()
            ->assertSee('data-delegation-reason', false)
            ->assertSee(WorkDelegationKind::Sickness->label())
            ->assertSee('Reha-Aufenthalt')
            ->assertSee(__('delegation.banner.represented_by', ['names' => __('delegation.banner.person_until', ['name' => 'Ben Muster', 'date' => '20.11.2026'])]));

        $this->actingAs($outsider)->get(route('delegations.index'))
            ->assertOk()
            ->assertDontSee('Anna Beispiel')
            ->assertDontSee('data-delegation-banner', false);
    }

    public function test_ending_by_the_people_involved_or_management_only(): void {
        $anna = $this->worker();
        $ben = $this->worker();
        $delegation = app(WorkDelegationService::class)->create($anna, $ben, CarbonImmutable::parse('2026-11-12'), null, WorkDelegationKind::Other, null, null, $anna);

        $this->actingAs($this->worker())->patch(route('delegations.end', $delegation))->assertForbidden();
        $this->actingAs($ben)->patch(route('delegations.end', $delegation))->assertRedirect(route('delegations.index'));
        $this->assertSame((int) $ben->id, $delegation->fresh()?->ended_by);
        $this->actingAs($anna)->patch(route('delegations.end', $delegation))->assertForbidden();
    }

    public function test_demo_block_seeds_and_purges_one_delegation(): void {
        $admin = $this->orgAdmin();
        $deputy = $this->worker();
        $users = collect([$admin, $deputy, $this->worker()]);
        $context = new DemoSeedContext($this->organization, $admin, $users, [], null, null, null);
        $block = app(DelegationDemoBlock::class);

        $this->assertTrue($block->supports($context));
        $this->assertSame(['work_delegations' => 1], $block->seed($context));
        $this->assertSame((int) $deputy->id, WorkDelegation::query()->sole()->deputy_user_id);

        $block->purge($this->organization);
        $this->assertSame(0, WorkDelegation::query()->count());
    }
}
