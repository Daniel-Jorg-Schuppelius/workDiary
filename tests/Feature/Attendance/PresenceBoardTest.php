<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PresenceBoardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Attendance;

use App\Enums\Attendance\{AttendanceSource, AttendanceStatus};
use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Models\{Attendance, User, Vacation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Anwesenheits-Board (MVP-524): Opt-in je Org, neutrale Abwesenheiten.
 */
class PresenceBoardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $employee;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->employee = $this->orgUser(['name' => 'Paula Praesent']);
    }

    private function enableBoard(): void {
        $this->organization->update(['settings' => ['presence' => ['board_enabled' => '1']]]);
        app()->instance('currentOrganization', $this->organization->fresh());
    }

    public function test_board_is_disabled_by_default(): void {
        $this->actingAs($this->employee)
            ->get(route('presence.board'))
            ->assertNotFound();
    }

    public function test_board_shows_present_users_when_enabled(): void {
        $this->enableBoard();
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->employee->id,
            'date' => now()->toDateString(),
            'started_at' => now()->subHours(2),
            'ended_at' => null,
            'status' => AttendanceStatus::Open->value,
            'source' => AttendanceSource::Clock->value,
        ]);

        $this->actingAs($this->employee)
            ->get(route('presence.board'))
            ->assertOk()
            ->assertSee('Paula Praesent');
    }

    public function test_absence_reason_is_never_shown(): void {
        $this->enableBoard();
        $absent = $this->orgUser(['name' => 'Udo Urlauber']);
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $absent->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $response = $this->actingAs($this->employee)
            ->get(route('presence.board'))
            ->assertOk()
            ->assertSee('Udo Urlauber');

        // Der Fehlgrund (Urlaub) taucht nirgends auf — neutral „abwesend".
        $response->assertDontSee(__('Urlaub') . '<');
    }
}
