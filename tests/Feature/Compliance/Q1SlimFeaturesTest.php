<?php
/*
 * Created on   : Thu Aug 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Q1SlimFeaturesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Compliance;

use App\Enums\Vacation\{VacationStatus, VacationType};
use App\Http\Controllers\Admin\AuditDiffController;
use App\Models\Absence\Vacation;
use App\Models\Audit\AuditLog;
use App\Models\Platform\User;
use App\Models\Reporting\SavedReportView;
use App\Models\Time\WorkSchedule;
use App\Support\{MorphMap, Sqid};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Q1-Restpunkte 527–529: Rückkehrzeit im Board, Versionsvergleich,
 * gespeicherte Report-Ansichten.
 */
class Q1SlimFeaturesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        // Montag — Rückkehrberechnungen brauchen stabile Wochentage.
        $this->travelTo(Carbon::parse('2026-06-15 10:00:00'));
        $this->setUpOrganization(['timezone' => 'UTC']);
    }

    // ── MVP-527: Rückkehrzeit im Board ───────────────────────────────────

    public function test_board_shows_return_date_for_absent_member(): void {
        $this->organization->update(['settings' => ['presence' => ['board_enabled' => '1']]]);
        app()->instance('currentOrganization', $this->organization->fresh());

        $absent = $this->orgUser(['name' => 'Rita Rueckkehr']);
        WorkSchedule::create([
            'organization_id' => $this->organization->id,
            'user_id' => $absent->id,
            'weekly_minutes' => 2400,
            'daily_target_minutes' => 480,
            'working_days' => [1, 2, 3, 4, 5],
            'break_after_minutes' => 360,
            'break_minutes' => 30,
            'valid_from' => '2020-01-01',
        ]);
        // Urlaub bis Freitag → Rückkehr am Montag, 22.06.
        Vacation::create([
            'organization_id' => $this->organization->id,
            'user_id' => $absent->id,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-19',
            'type' => VacationType::Vacation->value,
            'status' => VacationStatus::Approved->value,
        ]);

        $this->actingAs($this->orgUser())
            ->get(route('presence.board'))
            ->assertOk()
            ->assertSee('Rita Rueckkehr')
            ->assertSee(__('wieder ab :date', ['date' => '22.06.']));
    }

    // ── MVP-528: Versionsvergleich ───────────────────────────────────────

    public function test_audit_diff_shows_field_changes_between_states(): void {
        $admin = $this->orgAdmin();
        $member = $this->orgUser(['name' => 'Vera Version']);

        // Zwei auditierte Änderungen erzeugen.
        $member->update(['name' => 'Vera Verheiratet']);
        $member->update(['phone' => '0123 456789']);

        $logs = \App\Models\Audit\AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(User::class))
            ->where('auditable_id', $member->id)
            ->orderBy('id')
            ->get();
        $this->assertGreaterThanOrEqual(2, $logs->count());

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-diff.index', [
                'type' => 'member',
                'record' => \App\Support\Sqid::encode(User::class, (int) $member->id),
                'a' => $logs->first()->id,
                'b' => $logs->last()->id,
            ]))
            ->assertOk()
            ->assertSee(__('Unterschiede zwischen Stand A und Stand B'))
            ->assertSee('Vera Verheiratet');

        $response->assertDontSee('password');
    }

    public function test_audit_diff_is_admin_only(): void {
        $this->actingAs($this->orgUser())
            ->get(route('admin.audit-diff.index'))
            ->assertForbidden();
    }

    public function test_audit_diff_timeline_pages_instead_of_cutting_off(): void {
        $admin = $this->orgAdmin();
        $member = $this->orgUser(['name' => 'Stand 00']);
        $ids = $this->auditTrail($member, AuditDiffController::PER_PAGE + 1);
        $params = ['type' => 'member', 'record' => Sqid::encode(User::class, (int) $member->id)];

        $first = $this->actingAs($admin)->get(route('admin.audit-diff.index', $params))
            ->assertOk()
            ->assertSee(trans_choice(':count Eintrag|:count Einträge', count($ids), ['count' => count($ids)]));
        $this->assertSame(count($ids), $first->viewData('logs')->total());
        $this->assertSame(array_slice($ids, 0, AuditDiffController::PER_PAGE), $first->viewData('logs')->pluck('id')->all());

        $second = $this->actingAs($admin)->get(route('admin.audit-diff.index', $params + ['page' => 2]))->assertOk();
        $this->assertSame(array_slice($ids, AuditDiffController::PER_PAGE), $second->viewData('logs')->pluck('id')->all());
    }

    public function test_audit_diff_compares_states_on_different_pages(): void {
        $admin = $this->orgAdmin();
        $member = $this->orgUser(['name' => 'Stand 00']);
        $updates = AuditDiffController::PER_PAGE + 1;
        $ids = $this->auditTrail($member, $updates);
        $oldest = $ids[count($ids) - 1];
        $newest = $ids[0];
        $afterOldest = AuditLog::query()->findOrFail($ids[count($ids) - 2]);

        // A liegt auf Seite 2, B auf Seite 1 — vertauscht übergeben, der Controller ordnet A = älter.
        $response = $this->actingAs($admin)
            ->get(route('admin.audit-diff.index', [
                'type' => 'member',
                'record' => Sqid::encode(User::class, (int) $member->id),
                'a' => $newest,
                'b' => $oldest,
            ]))
            ->assertOk()
            ->assertSee(__('Unterschiede zwischen Stand A und Stand B'))
            ->assertSee('<input type="hidden" name="a" value="' . $oldest . '">', false);

        $this->assertSame($oldest, (int) $response->viewData('stateA')->id);
        $this->assertSame($newest, (int) $response->viewData('stateB')->id);
        $name = collect($response->viewData('diff'))->firstWhere('field', 'name');
        $this->assertSame((string) $afterOldest->changes['before']['name'], $name['before']);
        $this->assertSame(sprintf('Stand %02d', $updates), $name['after']);
    }

    /**
     * Benennt das Mitglied $updates-mal um.
     *
     * @return list<int> Audit-Ids des Mitglieds, neueste zuerst
     */
    private function auditTrail(User $member, int $updates): array {
        for ($i = 1; $i <= $updates; $i++) {
            $member->update(['name' => sprintf('Stand %02d', $i)]);
        }

        return AuditLog::query()
            ->where('auditable_type', MorphMap::stableKey(User::class))
            ->where('auditable_id', $member->id)
            ->orderByDesc('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    // ── MVP-529: gespeicherte Report-Ansichten ───────────────────────────

    public function test_saved_view_from_internal_report_url(): void {
        $user = $this->orgUser();

        $this->actingAs($user)
            ->post(route('report-views.store'), [
                'name' => 'Urlaubsplan 2026',
                'url' => route('reports.absence-calendar', ['year' => 2026]),
            ])
            ->assertRedirect();

        $view = SavedReportView::query()->firstOrFail();
        $this->assertSame('reports.absence-calendar', $view->route_name);
        $this->assertSame(['year' => '2026'], $view->params);
        $this->assertFalse($view->is_shared);
        $this->assertStringContainsString('year=2026', $view->targetUrl());
    }

    public function test_external_and_non_report_urls_are_rejected(): void {
        $user = $this->orgUser();

        $this->actingAs($user)
            ->from(route('report-views.index'))
            ->post(route('report-views.store'), [
                'name' => 'Böse',
                'url' => 'https://evil.example/reports/absences',
            ])
            ->assertSessionHas('error');

        $this->actingAs($user)
            ->from(route('report-views.index'))
            ->post(route('report-views.store'), [
                'name' => 'Keine Auswertung',
                'url' => route('presence.board'),
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, SavedReportView::query()->count());
    }

    public function test_shared_views_are_visible_to_others_but_not_editable(): void {
        $owner = $this->orgUser(['name' => 'Olga Owner']);
        $other = $this->orgUser();
        $view = SavedReportView::query()->create([
            'organization_id' => $this->organization->id,
            'created_by' => $owner->id,
            'name' => 'Team-Monatsblick',
            'route_name' => 'reports.absences',
            'params' => [],
            'is_shared' => true,
        ]);

        $this->actingAs($other)
            ->get(route('report-views.index'))
            ->assertOk()
            ->assertSee('Team-Monatsblick');

        $this->actingAs($other)
            ->post(route('report-views.toggle-share', $view))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('report-views.destroy', $view))
            ->assertRedirect();
        $this->assertSame(0, SavedReportView::query()->count());
    }
}
