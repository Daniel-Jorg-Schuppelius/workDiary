<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VacationOwnerGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{User, Vacation};
use App\Support\Sqid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Regression Mass-Assignment (Whitebox 2026-07): update() eines eigenen
 * Urlaubsantrags darf den Eigentümer (user_id) nicht umschreiben — analog
 * SickLeaveController. Nur ein Admin ändert user_id; ein leerer Wert lässt
 * den Bestand unangetastet (kein Orphan).
 */
class VacationOwnerGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function makePendingVacation(User $owner): Vacation {
        return Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $owner->id,
            'start_date' => '2030-06-01',
            'end_date' => '2030-06-05',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Pending->value,
        ]);
    }

    public function test_regular_user_cannot_reassign_owner_on_update(): void {
        $owner = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $vacation = $this->makePendingVacation($owner);

        $this->actingAs($owner)
            ->put(route('vacations.update', $vacation), [
                'user_id' => Sqid::encode(User::class, $colleague->id),
                'start_date' => '2030-06-01',
                'end_date' => '2030-06-06',
                'type' => VacationType::Vacation->value,
            ])
            ->assertRedirect();

        // Eigentümer unverändert, Zeitraum aktualisiert.
        $vacation->refresh();
        $this->assertSame($owner->id, $vacation->user_id);
        $this->assertSame('2030-06-06', $vacation->end_date->format('Y-m-d'));
    }
}
