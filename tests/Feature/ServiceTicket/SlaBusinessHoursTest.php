<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaBusinessHoursTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ServiceTicket;

use App\Enums\ServiceTicket\ServiceTicketPriority;
use App\Models\{Holiday, SlaContract};
use App\Services\HolidayService;
use App\Services\ServiceTicket\SlaTimer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 010 → Rang 41: SLA-Fristberechnung innerhalb der Geschäftszeiten.
 * Prüft, dass SLA-Minuten nur in den `business_hours`-Fenstern zählen
 * (Wochenenden/nicht belegte Tage + Feiertage übersprungen) und dass ohne
 * hinterlegtes Fenster weiter Kalenderzeit gilt (Alt-Verträge unverändert).
 */
final class SlaBusinessHoursTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    /** Mo–Fr 08:00–16:00 (480 Min./Tag). */
    private const HOURS = [
        ['weekday' => 1, 'from' => '08:00', 'to' => '16:00'],
        ['weekday' => 2, 'from' => '08:00', 'to' => '16:00'],
        ['weekday' => 3, 'from' => '08:00', 'to' => '16:00'],
        ['weekday' => 4, 'from' => '08:00', 'to' => '16:00'],
        ['weekday' => 5, 'from' => '08:00', 'to' => '16:00'],
    ];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * @param  array<int, array{weekday: int, from: string, to: string}>|null  $businessHours
     */
    private function contract(?array $businessHours): SlaContract {
        return SlaContract::factory()->create([
            'organization_id' => $this->organization->id,
            'business_hours' => $businessHours,
            'priority_table' => ['high' => ['reaction_minutes' => 60, 'resolution_minutes' => 120]],
        ]);
    }

    private function timer(): SlaTimer {
        return app(SlaTimer::class);
    }

    public function test_deadline_stays_within_same_business_day(): void {
        $reported = Carbon::parse('2025-06-16 09:00');
        $this->assertSame(1, $reported->dayOfWeekIso); // Montag

        $due = $this->timer()->computeDeadlines($this->contract(self::HOURS), ServiceTicketPriority::High, $reported);

        // 60 Min Reaktion → 10:00, 120 Min Lösung → 11:00 (beide im Fenster).
        $this->assertSame('2025-06-16 10:00', $due['reaction_due_at']?->format('Y-m-d H:i'));
        $this->assertSame('2025-06-16 11:00', $due['resolution_due_at']?->format('Y-m-d H:i'));
    }

    public function test_deadline_skips_the_weekend(): void {
        $reported = Carbon::parse('2025-06-20 15:30');
        $this->assertSame(5, $reported->dayOfWeekIso); // Freitag
        $this->assertSame(1, Carbon::parse('2025-06-23')->dayOfWeekIso); // folgender Montag

        $due = $this->timer()->computeDeadlines($this->contract(self::HOURS), ServiceTicketPriority::High, $reported);

        // Lösung 120 Min: Fr 15:30→16:00 = 30 Min, Rest 90 Min → Mo 08:00 + 90 = 09:30.
        $this->assertSame('2025-06-23 09:30', $due['resolution_due_at']?->format('Y-m-d H:i'));
    }

    public function test_deadline_skips_a_holiday(): void {
        Holiday::query()->create([
            'organization_id' => $this->organization->id,
            'date' => '2025-06-18',
            'name' => 'Betriebsruhe',
            'is_recurring' => false,
        ]);
        $reported = Carbon::parse('2025-06-18 09:00');
        $this->assertSame(3, $reported->dayOfWeekIso); // Mittwoch
        $this->assertTrue(app(HolidayService::class)->isHoliday($reported));

        $due = $this->timer()->computeDeadlines($this->contract(self::HOURS), ServiceTicketPriority::High, $reported);

        // Mittwoch ist Feiertag → komplett übersprungen; Do 08:00 + 120 = 10:00.
        $this->assertSame('2025-06-19 10:00', $due['resolution_due_at']?->format('Y-m-d H:i'));
    }

    public function test_without_business_hours_uses_calendar_time(): void {
        $reported = Carbon::parse('2025-06-21 23:00'); // Samstag, außerhalb jeder Geschäftszeit

        $due = $this->timer()->computeDeadlines($this->contract(null), ServiceTicketPriority::High, $reported);

        // Kein Fenster → reine Kalenderzeit: +120 Min = So 01:00.
        $this->assertSame('2025-06-22 01:00', $due['resolution_due_at']?->format('Y-m-d H:i'));
    }
}
