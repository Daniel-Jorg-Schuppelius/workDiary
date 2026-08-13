<?php
/*
 * Created on   : Wed Aug 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Feature103DeltasTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Schedule;

use App\Models\{ExternalWageItem, OnCallShift, ShiftRotation, ShiftType};
use App\Services\CoverageService;
use App\Services\Schedule\ShiftRotationRoller;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature-103-Deltas: Ideal-Besetzung/Gelb-Zone, Kombi-Dienste
 * (Rufbereitschaft aus Rollplan), externe Lohnarten-Positionen.
 */
class Feature103DeltasTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-15 08:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
    }

    public function test_cell_status_marks_tight_and_ideal_zones(): void {
        $service = app(CoverageService::class);

        // Ohne Ideal: exakt am Minimum = „gerade noch".
        $this->assertSame('tight', $service->cellStatus(2, 2, 5));
        $this->assertSame('ok', $service->cellStatus(3, 2, 5));
        $this->assertSame('under', $service->cellStatus(1, 2, 5));
        $this->assertSame('over', $service->cellStatus(6, 2, 5));
        // Fixwert (min == max): Minimum ist zugleich Soll → ok.
        $this->assertSame('ok', $service->cellStatus(2, 2, 2));
        // Mit Ideal: unterhalb Ideal = gelb, ab Ideal = ok.
        $this->assertSame('tight', $service->cellStatus(3, 2, 6, 4));
        $this->assertSame('ok', $service->cellStatus(4, 2, 6, 4));
    }

    public function test_combined_shift_creates_on_call_from_rotation(): void {
        $user = $this->orgUser();
        $late = ShiftType::create([
            'organization_id' => $this->organization->id,
            'name' => 'Spätdienst mit Rufbereitschaft',
            'abbreviation' => 'SR',
            'default_start_time' => '13:48',
            'default_end_time' => '22:00',
            'on_call_start_time' => '22:00',
            'on_call_end_time' => '06:00',
            'is_active' => true,
        ]);
        $rotation = ShiftRotation::create([
            'organization_id' => $this->organization->id,
            'name' => 'SR-Woche',
            'weeks_count' => 1,
            'is_active' => true,
        ]);
        $rotation->entries()->create(['week_index' => 0, 'iso_weekday' => 1, 'shift_type_id' => $late->id]);
        $rotation->assignments()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $user->id,
            'anchor_date' => '2026-06-15',
        ]);

        app(ShiftRotationRoller::class)->rollForward($this->organization, CarbonImmutable::parse('2026-06-15'), 1);

        $onCall = OnCallShift::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($onCall);
        $this->assertSame('2026-06-15 22:00', $onCall->start_at->format('Y-m-d H:i'));
        // Über Mitternacht → Ende am Folgetag.
        $this->assertSame('2026-06-16 06:00', $onCall->end_at->format('Y-m-d H:i'));

        // Idempotenz: zweiter Lauf erzeugt keine zweite Rufbereitschaft.
        app(ShiftRotationRoller::class)->rollForward($this->organization, CarbonImmutable::parse('2026-06-15'), 1);
        $this->assertSame(1, OnCallShift::query()->where('user_id', $user->id)->count());
    }

    public function test_wage_items_import_command(): void {
        $user = $this->orgUser(['personnel_number' => 'P-100']);
        $csv = tempnam(sys_get_temp_dir(), 'ewi') . '.csv';
        file_put_contents($csv, implode("\n", [
            'personnel_number;date;wage_type_code;quantity;unit;note',
            'P-100;2026-06-10;allowance.meal;5,00;day;Essensgeld',
            'P-100;2026-06-11;travel.km;42.5;km;',
            'UNBEKANNT;2026-06-10;x;1;;',
        ]));

        $this->artisan('wage-items:import', ['file' => $csv, '--org' => $this->organization->id])
            ->assertExitCode(0);

        $this->assertSame(2, ExternalWageItem::query()->count());
        $item = ExternalWageItem::query()->where('wage_type_code', 'allowance.meal')->firstOrFail();
        $this->assertSame($user->id, $item->user_id);
        $this->assertSame('5.00', (string) $item->quantity);

        // Wiederholter Import ersetzt statt dupliziert.
        $this->artisan('wage-items:import', ['file' => $csv, '--org' => $this->organization->id])
            ->assertExitCode(0);
        $this->assertSame(2, ExternalWageItem::query()->count());

        unlink($csv);
    }
}
