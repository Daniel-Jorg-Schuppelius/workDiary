<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WorkHandoverEntryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Delegation;

use App\Enums\User\{Permission, UserRole};
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\User;
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Einstieg zur endgültigen Übergabe auf der Seite „Vertretungen“ (Feature 202,
 * MVP-1648): Wer die Vertretungen leitet, die Mitgliederliste aber nicht sehen
 * darf, wählt die Person dort aus und landet nach dem Speichern wieder bei den
 * Vertretungen. Ohne Leitungsrecht gibt es den Einstieg nicht.
 */
final class WorkHandoverEntryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $lead;

    private User $anna;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->anna = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Anna Beispiel']);
        $this->lead = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Leitung Vertretung']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->lead->givePermissionTo(Permission::UserDelegationManage->value);
        $this->lead->unsetRelation('permissions');
    }

    public function test_a_lead_without_the_member_list_starts_the_handover_on_the_delegations_page(): void {
        $admin = $this->orgAdmin(['name' => 'Org Verwaltung']);
        $carl = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carl Probe']);
        $order = DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->anna->id,
            'assigned_user_id' => $this->anna->id,
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $this->actingAs($this->lead)->get(route('org.members.index'))->assertForbidden();
        $this->actingAs($this->lead)->get(route('delegations.index'))
            ->assertOk()
            ->assertSee(route('delegations.handover.choose'), false);

        $dialog = route('delegations.handover.dialog', [$this->anna, 'origin' => 'delegations']);
        $this->actingAs($this->lead)->get(route('delegations.handover.choose'))
            ->assertOk()
            ->assertSee($dialog, false)
            ->assertSee('Carl Probe')
            // Nie die eigene Arbeit, ohne Org-Admin-Rolle nicht die eines Admins.
            ->assertDontSee(route('delegations.handover.dialog', [$this->lead, 'origin' => 'delegations']), false)
            ->assertDontSee(route('delegations.handover.dialog', [$admin, 'origin' => 'delegations']), false);

        $this->actingAs($this->lead)->get($dialog)
            ->assertOk()
            ->assertSee('name="origin" value="delegations"', false);

        $this->actingAs($this->lead)->post(route('delegations.handover.store', $this->anna), [
            'handovers' => ['assigned_orders' => Sqid::encode(User::class, (int) $carl->id)],
            'origin' => 'delegations',
        ])->assertRedirect(route('delegations.index'))->assertSessionHas('success');
        $this->assertSame($carl->id, $order->fresh()?->assigned_user_id);
    }

    public function test_the_member_list_stays_the_return_path_without_the_marker(): void {
        $carl = $this->userWithRole(UserRole::User->value, attributes: ['name' => 'Carl Probe']);
        foreach (['Auftrag eins', 'Auftrag zwei'] as $title) {
            DiaryEntry::factory()->create([
                'organization_id' => $this->organization->id,
                'user_id' => $this->anna->id,
                'assigned_user_id' => $this->anna->id,
                'title' => $title,
                'start_at' => now(),
                'end_at' => now()->addHour(),
            ]);
        }

        // Nur die feste Herkunftsmarke zählt; ein Ziel aus der Eingabe wird nie angesteuert.
        $this->actingAs($this->orgAdmin())->post(route('delegations.handover.store', $this->anna), [
            'handovers' => ['assigned_orders' => Sqid::encode(User::class, (int) $carl->id)],
            'origin' => 'https://example.test/elsewhere',
        ])->assertRedirect(route('org.members.index'));
        $this->assertSame(2, DiaryEntry::query()->where('assigned_user_id', $carl->id)->count());
    }

    public function test_without_the_management_right_there_is_no_entry(): void {
        $this->actingAs($this->anna)->get(route('delegations.index'))
            ->assertOk()
            ->assertDontSee(route('delegations.handover.choose'), false);
        $this->actingAs($this->anna)->get(route('delegations.handover.choose'))->assertForbidden();
    }
}
