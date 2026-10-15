<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TodayPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Time;

use App\Models\Platform\User;
use App\Models\Time\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Heute-Seite mit Kernblock und „Weitere Details" (Feature 165, MVP-1192, E5):
 * nichts verschwindet, es ist nur eingeklappt; Abschlussweg bleibt vorn.
 */
class TodayPageTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->travelTo(CarbonImmutable::create(2026, 6, 10, 14, 0, 0));
        $this->user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
    }

    public function test_core_block_comes_first_and_details_keep_everything(): void {
        Attendance::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'date' => '2026-06-10',
            'started_at' => CarbonImmutable::parse('2026-06-10 08:00'),
            'ended_at' => CarbonImmutable::parse('2026-06-10 12:00'),
        ]);

        $html = $this->actingAs($this->user)->get(route('today.show'))->assertOk()->getContent();

        // Kernblock: Eingabeleiste, drei Kennzahlen, Hinweise, Zeiteinträge, Abschluss-Aktionen.
        // Weitere Details: nichts fehlt, nur eingeklappt.
        $expected = [
            __('Woran arbeiten Sie?'), __('Erfasst'), __('Anwesenheit'), __('Saldo'), __('day-close.section.issues'), __('Zeiteinträge'), __('day-close.action.close_day'),
            __('Weitere Details'), __('Soll'), __('Unverteilt'), __('Tagesfortschritt'), __('day-close.section.balance'), __('Stempelungen'),
        ];
        $missing = array_values(array_filter($expected, static fn (string $text): bool => ! str_contains($html, $text) && ! str_contains($html, e($text))));
        $this->assertSame([], $missing, 'Texte fehlen auf der Heute-Seite: ' . implode(' | ', $missing));
        $this->assertStringContainsString('data-collapsible="today.details"', $html);

        $entries = strpos($html, __('Zeiteinträge'));
        $details = strpos($html, __('Weitere Details'));
        $attendances = strpos($html, '<h2 class="font-[\'Space_Grotesk\'] text-sm font-semibold">' . __('Stempelungen'));
        $this->assertNotFalse($entries);
        $this->assertNotFalse($details);
        $this->assertNotFalse($attendances);
        $this->assertLessThan($details, $entries, 'Zeiteinträge stehen vor „Weitere Details".');
        $this->assertLessThan($attendances, $details, 'Stempelungen liegen unter „Weitere Details".');
    }

    public function test_future_day_has_no_entry_bar_but_still_renders(): void {
        $html = $this->actingAs($this->user)->get(route('today.show', ['date' => '2026-06-12']))->assertOk()->getContent();

        $this->assertStringNotContainsString(__('Woran arbeiten Sie?'), $html);
        $this->assertStringContainsString(__('Weitere Details'), $html);
        $this->assertStringContainsString(__('Saldo'), $html);
    }

    public function test_kpi_count_in_core_block_is_three(): void {
        $html = $this->actingAs($this->user)->get(route('today.show'))->assertOk()->getContent();

        $core = substr($html, 0, (int) strpos($html, __('Weitere Details')));
        $this->assertSame(1, substr_count($core, 'text-xs uppercase tracking-[0.18em] text-muted">' . __('Anwesenheit')));
        $this->assertSame(1, substr_count($core, 'text-xs uppercase tracking-[0.18em] text-muted">' . __('Saldo')));
        $this->assertSame(0, substr_count($core, 'text-xs uppercase tracking-[0.18em] text-muted">' . __('Unverteilt')));
    }
}
