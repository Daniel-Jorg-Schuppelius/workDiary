<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemEligibilityCheckerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Expense;

use App\Models\{PerDiemTrip, User};
use App\Services\Expense\{PerDiemCalculator, PerDiemEligibilityChecker};
use Database\Seeders\PerDiemRateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Whitebox 2026-07-10 (Z1): Der 4-Wochen-Reset der 3-Monats-Frist muss
 * feuern — mit Carbon 3 ist diffInDays signiert, die frühere Aufrufreihen-
 * folge lieferte immer negative Werte und der Reset griff nie.
 */
class PerDiemEligibilityCheckerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PerDiemRateSeeder::class);
        $this->setUpOrganization();
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    private function trip(string $start, string $end, bool $withDays = true): PerDiemTrip {
        $trip = PerDiemTrip::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'country' => 'DE',
            'purpose' => 'Montage',
            'location' => 'Frankfurt',
            'workplace_key' => 'frankfurt',
            'started_at' => $start,
            'ended_at' => $end,
        ]);
        if ($withDays) {
            $trip->days()->saveMany(app(PerDiemCalculator::class)->buildDays($trip));
        }

        return $trip;
    }

    public function test_gap_of_four_weeks_resets_used_days(): void {
        // Vor-Trip: 3 Tage bis 31.03.
        $this->trip('2025-03-29 08:00:00', '2025-03-31 18:00:00');

        // Neuer Trip startet > 4 Wochen später → Zähler muss bei 0 stehen.
        $next = $this->trip('2025-06-15 08:00:00', '2025-06-16 18:00:00', withDays: false);

        $result = app(PerDiemEligibilityChecker::class)->check($next);

        $this->assertTrue($result['eligible']);
        $this->assertSame(0, $result['used_days'], 'Unterbrechung ≥ 4 Wochen muss den Zähler zurücksetzen.');
    }

    public function test_short_gap_keeps_counting(): void {
        $this->trip('2025-03-29 08:00:00', '2025-03-31 18:00:00');

        // Nur 2 Wochen Pause → Vortage zählen weiter.
        $next = $this->trip('2025-04-14 08:00:00', '2025-04-15 18:00:00', withDays: false);

        $result = app(PerDiemEligibilityChecker::class)->check($next);

        $this->assertTrue($result['eligible']);
        $this->assertSame(3, $result['used_days']);
    }
}
