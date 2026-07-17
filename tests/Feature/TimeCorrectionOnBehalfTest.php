<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeCorrectionOnBehalfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\User\Permission as P;
use App\Models\{Attendance, Organization, TimeCorrectionRequest, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * „Im Namen von": Personalverwaltung/Teamleitung legt einen Korrekturantrag für
 * eine:n Mitarbeiter:in an (Eigentümer = Mitarbeiter, Antragsteller = HR). Bleibt
 * im selben Ledger und erfordert die Permission correction.create.others.
 */
class TimeCorrectionOnBehalfTest extends TestCase {
    use RefreshDatabase;

    protected function tearDown(): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        parent::tearDown();
    }

    /** @param list<P> $perms */
    private function grant(User $user, int $orgId, array $perms): void {
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgId);
        foreach ($perms as $p) {
            SpatiePermission::findOrCreate($p->value, 'web');
            $user->givePermissionTo($p->value);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function payload(int $targetUserId, Organization $org): array {
        return [
            'user_id' => \App\Support\Sqid::encode(User::class, $targetUserId),
            'scope_date' => '2026-06-01',
            'reason' => 'Stempelung am 01.06. vergessen – im Namen nachgetragen.',
            'items' => [[
                'target_type' => Attendance::class,
                'action' => 'create',
                'after' => json_encode([
                    'organization_id' => $org->id, 'user_id' => $targetUserId, 'date' => '2026-06-01',
                    'started_at' => '2026-06-01 08:00:00', 'ended_at' => '2026-06-01 16:00:00',
                    'duration_minutes' => 480, 'status' => 'closed',
                ]),
            ]],
        ];
    }

    public function test_hr_can_create_correction_on_behalf_of_employee(): void {
        $org = Organization::factory()->create();
        $hr = User::factory()->create(['organization_id' => $org->id]);
        $emp = User::factory()->create(['organization_id' => $org->id]);
        $this->grant($hr, $org->id, [P::CorrectionCreateOwn, P::CorrectionCreateForOthers]);

        $this->actingAs($hr)
            ->post(route('corrections.store'), $this->payload($emp->id, $org))
            ->assertRedirect();

        $req = TimeCorrectionRequest::where('user_id', $emp->id)->firstOrFail();
        $this->assertSame($hr->id, $req->requested_by_user_id, 'Antragsteller = HR.');
        $this->assertSame($emp->id, $req->user_id, 'Eigentümer = Mitarbeiter.');
    }

    public function test_without_permission_cannot_create_for_others(): void {
        $org = Organization::factory()->create();
        $plain = User::factory()->create(['organization_id' => $org->id]);
        $other = User::factory()->create(['organization_id' => $org->id]);
        $this->grant($plain, $org->id, [P::CorrectionCreateOwn]); // darf nur für sich selbst

        $this->actingAs($plain)
            ->post(route('corrections.store'), $this->payload($other->id, $org))
            ->assertForbidden();

        $this->assertSame(0, TimeCorrectionRequest::where('user_id', $other->id)->count());
    }
}
