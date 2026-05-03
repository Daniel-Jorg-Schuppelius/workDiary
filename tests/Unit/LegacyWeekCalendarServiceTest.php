<?php

namespace Tests\Unit;

use App\Services\Legacy\LegacyWeekCalendarService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class LegacyWeekCalendarServiceTest extends TestCase {
    public function test_resolve_window_prefers_iso_week_input(): void {
        $service = new LegacyWeekCalendarService();

        $result = $service->resolveWindow(0, '2026-W18');

        $this->assertSame('2026-W18', $result['selectedWeek']);
        $this->assertSame('2026-04-27', $result['monday']->toDateString());
        $this->assertSame('2026-05-03', $result['sunday']->toDateString());
    }

    public function test_build_week_maps_indexes_entries_and_flags_per_day(): void {
        $service = new LegacyWeekCalendarService();

        $entry = (object) [
            'user' => 7,
            'von' => CarbonImmutable::parse('2026-05-01 08:00'),
            'bis' => CarbonImmutable::parse('2026-05-03 10:00'),
            'inhalt' => 'Test',
        ];
        $oncall = (object) [
            'user' => 7,
            'von' => CarbonImmutable::parse('2026-05-02'),
            'bis' => CarbonImmutable::parse('2026-05-03'),
        ];
        $notdienst = (object) [
            'user' => 8,
            'von' => '2026-05-01',
            'bis' => '2026-05-01',
        ];

        $result = $service->buildWeekMaps([$entry], [$oncall], [$notdienst]);

        $this->assertCount(1, $result['entriesByUserDay'][7]['2026-05-01']);
        $this->assertCount(1, $result['entriesByUserDay'][7]['2026-05-02']);
        $this->assertCount(1, $result['entriesByUserDay'][7]['2026-05-03']);
        $this->assertTrue($result['oncallByUserDay'][7]['2026-05-02']);
        $this->assertTrue($result['oncallByUserDay'][7]['2026-05-03']);
        $this->assertTrue($result['notdienstByUserDay'][8]['2026-05-01']);
    }
}
