<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DateRangeContextTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\UI;

use App\Services\UI\DateRangeContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateRangeContextTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 5, 15, 12, 0, 0));
    }

    protected function tearDown(): void {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_default_is_current_month(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_THIS_MONTH, $range['preset']);
        $this->assertSame('2026-05-01', $range['from']->toDateString());
        $this->assertSame('2026-05-31', $range['to']->toDateString());
    }

    public function test_today_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_TODAY);
        $range = $ctx->current();

        $this->assertSame('2026-05-15', $range['from']->toDateString());
        $this->assertSame('2026-05-15', $range['to']->toDateString());
    }

    public function test_last_month_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_LAST_MONTH);
        $range = $ctx->current();

        $this->assertSame('2026-04-01', $range['from']->toDateString());
        $this->assertSame('2026-04-30', $range['to']->toDateString());
    }

    public function test_custom_range_persists_dates(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_CUSTOM, '2026-01-15', '2026-02-20');
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_CUSTOM, $range['preset']);
        $this->assertSame('2026-01-15', $range['from']->toDateString());
        $this->assertSame('2026-02-20', $range['to']->toDateString());
    }

    public function test_custom_range_swaps_inverted_dates(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_CUSTOM, '2026-03-10', '2026-03-01');
        $range = $ctx->current();

        // Inverted "to" gets clamped up to "from".
        $this->assertSame('2026-03-10', $range['from']->toDateString());
        $this->assertSame('2026-03-10', $range['to']->toDateString());
    }

    public function test_invalid_preset_falls_back_to_this_month(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set('garbage');
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_THIS_MONTH, $range['preset']);
    }

    public function test_last_7_days_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_LAST_7_DAYS);
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_LAST_7_DAYS, $range['preset']);
        $this->assertSame('2026-05-09', $range['from']->toDateString());
        $this->assertSame('2026-05-15', $range['to']->toDateString());
    }

    public function test_last_30_days_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_LAST_30_DAYS);
        $range = $ctx->current();

        $this->assertSame('2026-04-16', $range['from']->toDateString());
        $this->assertSame('2026-05-15', $range['to']->toDateString());
    }

    public function test_last_90_days_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_LAST_90_DAYS);
        $range = $ctx->current();

        $this->assertSame('2026-02-15', $range['from']->toDateString());
        $this->assertSame('2026-05-15', $range['to']->toDateString());
    }

    public function test_this_quarter_preset(): void {
        // 2026-05-15 liegt in Q2 (Apr–Jun).
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_THIS_QUARTER);
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_THIS_QUARTER, $range['preset']);
        $this->assertSame('2026-04-01', $range['from']->toDateString());
        $this->assertSame('2026-06-30', $range['to']->toDateString());
        $this->assertSame('quarter', $range['unit']);
    }

    public function test_last_quarter_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_LAST_QUARTER);
        $range = $ctx->current();

        $this->assertSame('2026-01-01', $range['from']->toDateString());
        $this->assertSame('2026-03-31', $range['to']->toDateString());
        $this->assertSame('quarter', $range['unit']);
    }

    public function test_quarter_shifts_by_whole_quarter(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_THIS_QUARTER);
        $ctx->shift(-1);
        $range = $ctx->current();

        // Q2 → Q1; als Custom persistiert, aber wieder als Quartal erkannt.
        $this->assertSame('2026-01-01', $range['from']->toDateString());
        $this->assertSame('2026-03-31', $range['to']->toDateString());
        $this->assertSame('quarter', $range['unit']);
        $this->assertSame(DateRangeContext::PRESET_LAST_QUARTER, $range['effectivePreset']);
    }

    public function test_custom_quarter_range_detected_as_preset(): void {
        $ctx = $this->app->make(DateRangeContext::class);
        $ctx->set(DateRangeContext::PRESET_CUSTOM, '2026-04-01', '2026-06-30');
        $range = $ctx->current();

        $this->assertSame(DateRangeContext::PRESET_THIS_QUARTER, $range['effectivePreset']);
    }
}
