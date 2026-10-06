<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CrisisStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Crisis;

use App\Enums\Crisis\{CrisisActionStatus, CrisisCaseStatus, CrisisCommunicationStatus, CrisisContinuityImpactStatus};
use App\Models\Crisis\{CrisisAction, CrisisCase, CrisisCommunication, CrisisContinuityImpact, CrisisDeadlineTemplate};
use App\Models\Platform\User;
use App\Services\Crisis\{CrisisBcmReportBuilder, CrisisDeadlineService, CrisisStatusPageService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 6): Krisenakte, Maßnahme,
 * Kommunikation und Wiederanlauf führen ihren Status als Enum. Ein Vergleich
 * gegen die frühere Zeichenkette ist danach still falsch — die Krise ließe
 * sich nicht mehr aktivieren, die Aktion fehlte oder bliebe stehen.
 */
final class CrisisStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private User $second;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function crisis(CrisisCaseStatus $status, array $attributes = []): CrisisCase {
        return CrisisCase::query()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Ransomware-Verdacht',
            'category' => 'security',
            'severity' => 'critical',
            'status' => $status,
            'created_by' => $this->admin->id,
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function action(CrisisCase $case, CrisisActionStatus $status, array $attributes = []): CrisisAction {
        return $case->actions()->create(array_replace([
            'organization_id' => $this->organization->id,
            'title' => 'Netz trennen',
            'priority' => 'high',
            'status' => $status,
        ], $attributes));
    }

    private function communication(CrisisCase $case, CrisisCommunicationStatus $status, string $audience = 'public'): CrisisCommunication {
        return $case->communications()->create([
            'organization_id' => $this->organization->id,
            'audience' => $audience,
            'subject' => 'Störungsinfo ' . $status->value,
            'body' => 'Wir arbeiten daran.',
            'status' => $status,
            'sent_at' => $status === CrisisCommunicationStatus::Sent ? now() : null,
            'created_by' => $this->second->id,
        ]);
    }

    private function impact(CrisisCase $case, CrisisContinuityImpactStatus $status): CrisisContinuityImpact {
        return $case->continuityImpacts()->create([
            'organization_id' => $this->organization->id,
            'process_name' => 'Auftragsannahme ' . $status->value,
            'status' => $status,
        ]);
    }

    private function page(string $url): string {
        return (string) $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    /** Eigene Meldung statt `assertSee`: Dessen Fehlertext trägt die ganze Seite. */
    private function assertPageHas(string $needle, string $html): void {
        $this->assertTrue(str_contains($html, $needle), "Auf der Seite fehlt: {$needle}");
    }

    private function assertPageLacks(string $needle, string $html): void {
        $this->assertFalse(str_contains($html, $needle), "Auf der Seite steht unerwartet: {$needle}");
    }

    private function assertBadge(string $tone, string $label, string $html): void {
        $this->assertSame(1, preg_match('/badge-' . $tone . '[^>]*>\s*' . preg_quote(e($label), '/') . '\s*</u', $html), "Kein Abzeichen „{$label}“ im Ton {$tone}.");
    }

    // ── Krisenakte ───────────────────────────────────────────────────────

    public function test_case_file_offers_the_lifecycle_action_that_fits_the_status(): void {
        $reported = $this->crisis(CrisisCaseStatus::Reported);
        $html = $this->page(route('crisis.show', $reported));
        $this->assertPageHas('action="' . route('crisis.activate', $reported) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.all-clear', $reported) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.status', $reported) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.close', $reported) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.review.store', $reported) . '"', $html);

        // Aktivierte Akte im Wiederanlauf: kein zweiter Alarm (eine nie alarmierte Akte behielte den Knopf).
        $recovery = $this->crisis(CrisisCaseStatus::Recovery, ['activated_at' => now()->subDay()]);
        $html = $this->page(route('crisis.show', $recovery));
        $this->assertPageLacks('action="' . route('crisis.activate', $recovery) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.all-clear', $recovery) . '"', $html);
        $this->assertPageHas('<option value="recovery" selected>' . e(CrisisCaseStatus::Recovery->label()) . '</option>', $html);
        $this->assertPageLacks('<option value="assessed" selected>', $html);

        $allClear = $this->crisis(CrisisCaseStatus::AllClear);
        $html = $this->page(route('crisis.show', $allClear));
        $this->assertPageLacks('action="' . route('crisis.all-clear', $allClear) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.status', $allClear) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.review.store', $allClear) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.sitrep.store', $allClear) . '"', $html);

        $postReview = $this->crisis(CrisisCaseStatus::PostReview);
        $this->assertPageHas('action="' . route('crisis.close', $postReview) . '"', $this->page(route('crisis.show', $postReview)));

        // Geschlossene und verworfene Akten nehmen nichts mehr auf.
        foreach ([CrisisCaseStatus::Closed, CrisisCaseStatus::Discarded] as $shelved) {
            $case = $this->crisis($shelved);
            $html = $this->page(route('crisis.show', $case));
            $this->assertPageLacks('action="' . route('crisis.sitrep.store', $case) . '"', $html);
            $this->assertPageLacks('action="' . route('crisis.decisions.store', $case) . '"', $html);
            $this->assertPageLacks('action="' . route('crisis.room.points.store', $case) . '"', $html);
            $this->assertPageLacks('action="' . route('crisis.close', $case) . '"', $html);
        }
    }

    public function test_activation_all_clear_and_review_follow_the_status_table(): void {
        $case = $this->crisis(CrisisCaseStatus::Reported);

        // Nachbereitung erst nach der Entwarnung.
        $this->actingAs($this->admin)->post(route('crisis.review.store', $case), ['summary' => 'Verlauf'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.activate', $case))->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::Activated, $case->fresh()->status);
        $this->assertNotNull($case->fresh()->activated_at);
        // Eine aktivierte Krise wird nicht erneut aktiviert.
        $this->actingAs($this->admin)->post(route('crisis.activate', $case))->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('crisis.all-clear', $case))->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::AllClear, $case->fresh()->status);
        $this->actingAs($this->admin)->post(route('crisis.all-clear', $case))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.activate', $case))->assertSessionHas('error');

        $this->actingAs($this->admin)->post(route('crisis.review.store', $case), ['summary' => 'Verlauf'])->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::PostReview, $case->fresh()->status);

        // Aus „bewertet“ lässt sich ebenfalls aktivieren, aus „vorbereitet“ weder aktivieren noch entwarnen.
        $assessed = $this->crisis(CrisisCaseStatus::Assessed);
        $this->actingAs($this->admin)->post(route('crisis.activate', $assessed))->assertSessionHas('status');
        $prepared = $this->crisis(CrisisCaseStatus::Prepared);
        $this->actingAs($this->admin)->post(route('crisis.activate', $prepared))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.all-clear', $prepared))->assertSessionHas('error');
        $this->assertSame(CrisisCaseStatus::Prepared, $prepared->fresh()->status);
    }

    public function test_a_file_worked_without_alarm_can_still_be_activated_once(): void {
        // Ohne Alarm begonnen: gemeldet → in Bearbeitung, nie aktiviert.
        $quiet = $this->crisis(CrisisCaseStatus::InProgress);
        $this->assertNull($quiet->activated_at);
        $this->assertPageHas('action="' . route('crisis.activate', $quiet) . '"', $this->page(route('crisis.show', $quiet)));

        $this->actingAs($this->admin)->post(route('crisis.activate', $quiet))->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::Activated, $quiet->fresh()->status);
        $this->assertNotNull($quiet->fresh()->activated_at);

        // Einmal aktiviert und wieder in einem Lagezustand: kein zweiter Alarm, kein Knopf, Fristenbeginn bleibt.
        $this->actingAs($this->admin)->post(route('crisis.status', $quiet), ['status' => 'stabilized'])->assertSessionHas('status');
        $activatedAt = $quiet->fresh()->activated_at;
        $this->travel(2)->hours();
        $this->assertPageLacks('action="' . route('crisis.activate', $quiet) . '"', $this->page(route('crisis.show', $quiet)));
        $this->actingAs($this->admin)->post(route('crisis.activate', $quiet))
            ->assertSessionHas('error', (string) __('Die Akte wurde bereits aktiviert.'));
        $this->assertSame(CrisisCaseStatus::Stabilized, $quiet->fresh()->status);
        $this->assertTrue($activatedAt->equalTo($quiet->fresh()->activated_at));
    }

    public function test_manual_status_accepts_exactly_the_stages(): void {
        $case = $this->crisis(CrisisCaseStatus::Activated);

        // „Verworfen“ hat keinen Knopf und ist deshalb auch kein Ziel der Statuspflege.
        foreach (['prepared', 'reported', 'activated', 'all_clear', 'post_review', 'closed', 'discarded', 'unbekannt', ''] as $rejected) {
            $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => $rejected])->assertSessionHasErrors('status');
        }
        $this->assertSame(CrisisCaseStatus::Activated, $case->fresh()->status);
        $this->assertSame(['assessed', 'in_progress', 'stabilized', 'recovery'], array_column(CrisisCaseStatus::stages(), 'value'));
        // Einmal aktiviert, nie wieder „bewertet“ — aus keinem aktiven Stand.
        foreach ([CrisisCaseStatus::InProgress, CrisisCaseStatus::Stabilized, CrisisCaseStatus::Recovery] as $stage) {
            $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => 'assessed'])
                ->assertSessionHas('error', (string) __('Statuswechsel von :from nach :to ist nicht zulässig.', ['from' => $case->fresh()->status->label(), 'to' => CrisisCaseStatus::Assessed->label()]))
                ->assertSessionMissing('status');
            $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => $stage->value])->assertSessionHas('status');
            $this->assertSame($stage, $case->fresh()->status);
        }
        $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => 'assessed'])->assertSessionHas('error');
        $this->assertSame(CrisisCaseStatus::Recovery, $case->fresh()->status);
        // Vor der Aktivierung bleibt „bewertet“ erreichbar.
        $reported = $this->crisis(CrisisCaseStatus::Reported);
        $this->actingAs($this->admin)->post(route('crisis.status', $reported), ['status' => 'assessed'])->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::Assessed, $reported->fresh()->status);
        // Derselbe Stand noch einmal: keine Meldung, kein weiterer Protokolleintrag.
        $entries = $case->auditLogs()->count();
        $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => 'recovery'])->assertSessionHas('status')->assertSessionMissing('error');
        $this->assertSame($entries, $case->auditLogs()->count());
    }

    public function test_status_table_is_the_intended_lifecycle(): void {
        $table = [];
        foreach (CrisisCaseStatus::cases() as $status) {
            $table[$status->value] = array_column($status->allowedTransitions(), 'value');
        }

        $this->assertSame([
            'prepared' => [],
            'reported' => ['assessed', 'in_progress', 'stabilized', 'recovery', 'all_clear', 'activated'],
            'assessed' => ['in_progress', 'stabilized', 'recovery', 'all_clear', 'activated'],
            // Einmal aktiviert, nie wieder „bewertet“: sonst ließe sich erneut aktivieren und die Meldefristen begännen von vorn.
            // „aktiviert“ bleibt aus den Lagezuständen erreichbar — ob die Akte schon aktiviert war, prüft canBeActivated().
            'activated' => ['in_progress', 'stabilized', 'recovery', 'all_clear'],
            'in_progress' => ['stabilized', 'recovery', 'all_clear', 'activated'],
            'stabilized' => ['in_progress', 'recovery', 'all_clear', 'activated'],
            'recovery' => ['in_progress', 'stabilized', 'all_clear', 'activated'],
            'all_clear' => ['post_review'],
            'post_review' => ['closed'],
            'closed' => [],
            'discarded' => [],
        ], $table);
    }

    public function test_manual_status_is_refused_wherever_the_file_hides_the_selection(): void {
        foreach ([CrisisCaseStatus::Closed, CrisisCaseStatus::Discarded, CrisisCaseStatus::AllClear, CrisisCaseStatus::PostReview, CrisisCaseStatus::Prepared] as $locked) {
            $case = $this->crisis($locked);
            $this->assertPageLacks('action="' . route('crisis.status', $case) . '"', $this->page(route('crisis.show', $case)));
            $entries = $case->auditLogs()->count();

            foreach (CrisisCaseStatus::stages() as $stage) {
                $response = $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => $stage->value]);
                if ($locked->isShelved()) {
                    // Geschlossen und verworfen weist die Policy ab, bevor die Statustabelle zählt.
                    $response->assertForbidden();
                } else {
                    $response->assertSessionHas('error', (string) __('Statuswechsel von :from nach :to ist nicht zulässig.', ['from' => $locked->label(), 'to' => $stage->label()]))
                        ->assertSessionMissing('status');
                }
            }

            $this->assertSame($locked, $case->fresh()->status, $locked->value);
            $this->assertSame($entries, $case->auditLogs()->count(), $locked->value);
        }
    }

    public function test_close_needs_the_reviewed_state(): void {
        $review = fn (CrisisCase $case) => $case->review()->create([
            'organization_id' => $this->organization->id, 'summary' => 'Verlauf', 'reviewed_by' => $this->admin->id, 'reviewed_at' => now(),
        ]);

        // Eine Nachbereitung allein genügt nicht: geschlossen wird nur aus „nachbereitet“.
        foreach (CrisisCaseStatus::cases() as $status) {
            if ($status === CrisisCaseStatus::PostReview) {
                continue;
            }
            $case = $this->crisis($status, ['closed_at' => $status === CrisisCaseStatus::Closed ? now()->subWeek() : null]);
            $review($case);
            $entries = $case->auditLogs()->count();

            $response = $this->actingAs($this->admin)->post(route('crisis.close', $case));
            if ($status->isShelved()) {
                $response->assertForbidden();
            } else {
                $response->assertSessionHas('error')->assertSessionMissing('status');
            }

            $this->assertSame($status, $case->fresh()->status, $status->value);
            $this->assertSame($case->closed_at?->toDateTimeString(), $case->fresh()->closed_at?->toDateTimeString(), $status->value);
            $this->assertSame($entries, $case->auditLogs()->count(), $status->value);
        }

        $reviewed = $this->crisis(CrisisCaseStatus::PostReview);
        $this->actingAs($this->admin)->post(route('crisis.close', $reviewed))->assertSessionHas('error');
        $review($reviewed);
        $this->actingAs($this->admin)->post(route('crisis.close', $reviewed))->assertSessionHas('status');
        $this->assertSame(CrisisCaseStatus::Closed, $reviewed->fresh()->status);
        $this->assertNotNull($reviewed->fresh()->closed_at);
    }

    public function test_stage_selection_starts_empty_when_the_status_is_no_stage(): void {
        $placeholder = '<option value="" selected disabled>' . e((string) __('Bitte wählen')) . '</option>';

        // Gemeldet und aktiviert sind keine Lagezustände: das Feld darf keinen vortäuschen.
        foreach ([CrisisCaseStatus::Reported, CrisisCaseStatus::Activated] as $status) {
            $case = $this->crisis($status);
            $html = $this->page(route('crisis.show', $case));
            $this->assertPageHas($placeholder, $html);
            foreach ($status->selectableStages() as $stage) {
                $this->assertPageHas('<option value="' . $stage->value . '"', $html);
                $this->assertPageLacks('<option value="' . $stage->value . '" selected>', $html);
            }
            // Der Stand selbst steht weiter im Kopf.
            $this->assertBadge('outline', $status->label(), $html);

            // Ohne Wahl abgesendet: Fehler, der Stand bleibt.
            $this->actingAs($this->admin)->post(route('crisis.status', $case), ['status' => ''])->assertSessionHasErrors('status');
            $this->actingAs($this->admin)->post(route('crisis.status', $case), [])->assertSessionHasErrors('status');
            $this->assertSame($status, $case->fresh()->status);
        }

        foreach (CrisisCaseStatus::stages() as $stage) {
            $html = $this->page(route('crisis.show', $this->crisis($stage)));
            $this->assertPageLacks($placeholder, $html);
            $this->assertPageHas('<option value="' . $stage->value . '" selected>' . e($stage->label()) . '</option>', $html);
        }
    }

    public function test_stage_selection_offers_only_reachable_stages(): void {
        $this->assertSame(['assessed', 'in_progress', 'stabilized', 'recovery'], array_column(CrisisCaseStatus::Reported->selectableStages(), 'value'));
        $this->assertSame(['in_progress', 'stabilized', 'recovery'], array_column(CrisisCaseStatus::Activated->selectableStages(), 'value'));
        $this->assertSame(['in_progress', 'stabilized', 'recovery'], array_column(CrisisCaseStatus::Stabilized->selectableStages(), 'value'));
        $this->assertSame([], array_column(CrisisCaseStatus::AllClear->selectableStages(), 'value'));

        // Auswahl und Server sagen dasselbe: nur der aktuelle und die laut Tabelle erreichbaren Lagezustände stehen im Feld.
        foreach (CrisisCaseStatus::active() as $status) {
            $html = $this->page(route('crisis.show', $this->crisis($status)));
            foreach (CrisisCaseStatus::stages() as $stage) {
                $offered = $stage === $status || $status->canTransitionTo($stage);
                $this->assertSame($offered, str_contains($html, '<option value="' . $stage->value . '"'), "{$status->value} → {$stage->value}");
            }
        }
    }

    public function test_list_filters_by_status_and_counts_active_cases_and_overdue_actions(): void {
        $active = $this->crisis(CrisisCaseStatus::Stabilized, ['title' => 'Laufende Krise']);
        $this->crisis(CrisisCaseStatus::Closed, ['title' => 'Alte Krise']);
        $this->action($active, CrisisActionStatus::InProgress, ['due_at' => now()->subDay()]);
        $this->action($active, CrisisActionStatus::Done, ['due_at' => now()->subDay()]);
        $this->action($active, CrisisActionStatus::Open, ['due_at' => now()->addDay()]);

        $response = $this->actingAs($this->admin)->get(route('crisis.index', ['status' => 'closed']))->assertOk()
            ->assertViewHas('activeCount', 1)
            ->assertViewHas('overdueActions', 1)
            ->assertViewHas('cases', fn ($cases): bool => $cases->pluck('title')->all() === ['Alte Krise']);
        $html = (string) $response->getContent();
        $this->assertPageHas('<option value="closed" selected>' . e(CrisisCaseStatus::Closed->label()) . '</option>', $html);
        $this->assertBadge('outline', CrisisCaseStatus::Closed->label(), $html);
        foreach (CrisisCaseStatus::cases() as $status) {
            $this->assertPageHas('<option value="' . $status->value . '"', $html);
        }

        // Unbekannter Filterwert: ungefiltert.
        $this->actingAs($this->admin)->get(route('crisis.index', ['status' => 'unbekannt']))->assertOk()
            ->assertViewHas('cases', fn ($cases): bool => $cases->total() === 2)
            ->assertViewHas('filters', ['status' => '']);
    }

    public function test_deadlines_stop_being_overdue_once_the_crisis_has_ended(): void {
        CrisisDeadlineTemplate::query()->create([
            'organization_id' => null, 'category' => 'security', 'label' => 'Erstmeldung', 'offset_hours' => 24, 'source' => '§ 32 BSIG', 'active' => true,
        ]);
        $service = app(CrisisDeadlineService::class);
        $overdue = fn (CrisisCaseStatus $status): bool => $service->deadlinesFor($this->crisis($status, ['activated_at' => now()->subDays(3)]))[0]['overdue'];

        foreach (CrisisCaseStatus::cases() as $status) {
            $ended = in_array($status, [CrisisCaseStatus::AllClear, CrisisCaseStatus::PostReview, CrisisCaseStatus::Closed, CrisisCaseStatus::Discarded], true);
            $this->assertSame(! $ended, $overdue($status), $status->value);
        }
    }

    // ── Maßnahmen ────────────────────────────────────────────────────────

    public function test_action_list_reflects_the_stored_status(): void {
        $case = $this->crisis(CrisisCaseStatus::Activated);
        $running = $this->action($case, CrisisActionStatus::InProgress, ['due_at' => now()->subDay()]);
        $done = $this->action($case, CrisisActionStatus::Done, ['due_at' => now()->subDay()]);
        $cancelled = $this->action($case, CrisisActionStatus::Cancelled);

        $html = $this->page(route('crisis.show', $case));

        $this->assertPageHas('action="' . route('crisis.actions.update', [$case, $running]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.actions.update', [$case, $done]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.actions.update', [$case, $cancelled]) . '"', $html);
        $this->assertSame(1, substr_count($html, '<option value="in_progress" selected>'));
        $this->assertPageLacks('<option value="open" selected>', $html);
        // Durchgestrichen sind die erledigte und die verworfene Maßnahme, überfällig ist nur die laufende.
        $this->assertSame(2, substr_count($html, 'line-through opacity-60'));
        $this->assertSame(1, substr_count($html, 'text-xs text-error font-semibold'));
    }

    public function test_action_update_accepts_every_status_and_nothing_else(): void {
        $case = $this->crisis(CrisisCaseStatus::Activated);
        $action = $this->action($case, CrisisActionStatus::InProgress, ['due_at' => now()->subDay()]);

        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $action]), ['status' => 'erledigt'])->assertSessionHasErrors('status');
        foreach ([CrisisActionStatus::Done, CrisisActionStatus::Cancelled, CrisisActionStatus::InProgress] as $status) {
            $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $action]), ['status' => $status->value])->assertSessionHasNoErrors();
            $this->assertSame($status, $action->fresh()->status);
        }
        $this->assertNull($action->fresh()->escalated_at);
        // Zurück auf „offen“ bei überschrittener Frist setzt die Eskalationsmarke.
        $this->actingAs($this->admin)->put(route('crisis.actions.update', [$case, $action]), ['status' => 'open'])->assertSessionHasNoErrors();
        $this->assertSame(CrisisActionStatus::Open, $action->fresh()->status);
        $this->assertNotNull($action->fresh()->escalated_at);
    }

    // ── Kommunikation ────────────────────────────────────────────────────

    public function test_communication_badges_and_actions_follow_the_status(): void {
        $case = $this->crisis(CrisisCaseStatus::Activated);
        $draft = $this->communication($case, CrisisCommunicationStatus::Draft);
        $approved = $this->communication($case, CrisisCommunicationStatus::Approved);
        $sent = $this->communication($case, CrisisCommunicationStatus::Sent);

        $html = $this->page(route('crisis.show', $case));

        $this->assertPageHas('action="' . route('crisis.communications.approve', [$case, $draft]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.communications.approve', [$case, $approved]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.communications.approve', [$case, $sent]) . '"', $html);
        $this->assertPageHas('action="' . route('crisis.communications.sent', [$case, $approved]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.communications.sent', [$case, $draft]) . '"', $html);
        $this->assertPageLacks('action="' . route('crisis.communications.sent', [$case, $sent]) . '"', $html);
        $this->assertBadge('ghost', CrisisCommunicationStatus::Draft->label(), $html);
        $this->assertBadge('info', CrisisCommunicationStatus::Approved->label(), $html);
        $this->assertBadge('success', CrisisCommunicationStatus::Sent->label(), $html);
    }

    public function test_communication_is_approved_and_sent_in_that_order_only(): void {
        $case = $this->crisis(CrisisCaseStatus::Activated);
        $communication = $this->communication($case, CrisisCommunicationStatus::Draft);

        $this->actingAs($this->admin)->post(route('crisis.communications.sent', [$case, $communication]), ['channel' => 'Mail'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.communications.approve', [$case, $communication]))->assertSessionHas('status');
        $this->assertSame(CrisisCommunicationStatus::Approved, $communication->fresh()->status);
        // Freigegeben ist kein Entwurf mehr.
        $this->actingAs($this->admin)->post(route('crisis.communications.approve', [$case, $communication]))->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('crisis.communications.sent', [$case, $communication]), ['channel' => 'Mail'])->assertSessionHas('status');
        $this->assertSame(CrisisCommunicationStatus::Sent, $communication->fresh()->status);
        $this->actingAs($this->admin)->post(route('crisis.communications.sent', [$case, $communication]), ['channel' => 'Mail'])->assertSessionHas('error');
    }

    public function test_status_page_marks_only_notices_of_ended_crises_as_resolved(): void {
        $this->communication($this->crisis(CrisisCaseStatus::Stabilized), CrisisCommunicationStatus::Sent);
        $this->communication($this->crisis(CrisisCaseStatus::AllClear, ['all_clear_at' => now()->subDay()]), CrisisCommunicationStatus::Sent);
        $this->communication($this->crisis(CrisisCaseStatus::Activated), CrisisCommunicationStatus::Approved);

        $notices = app(CrisisStatusPageService::class)->notices($this->organization, ['public']);

        $this->assertCount(2, $notices);
        $badges = array_map(static fn ($notice): ?string => $notice->badge, $notices);
        $this->assertEqualsCanonicalizing([null, (string) __('crisis.status_page.resolved')], $badges);
        $this->assertEqualsCanonicalizing(['warning', 'success'], array_map(static fn ($notice): string => $notice->tone, $notices));
    }

    // ── Wiederanlauf ─────────────────────────────────────────────────────

    public function test_continuity_impacts_show_their_tone_and_preselect_the_status(): void {
        $case = $this->crisis(CrisisCaseStatus::Recovery);
        foreach ([CrisisContinuityImpactStatus::Down, CrisisContinuityImpactStatus::Workaround, CrisisContinuityImpactStatus::Restored] as $status) {
            $this->impact($case, $status);
        }

        $html = $this->page(route('crisis.show', $case));

        $this->assertBadge('error', CrisisContinuityImpactStatus::Down->label(), $html);
        $this->assertBadge('warning', CrisisContinuityImpactStatus::Workaround->label(), $html);
        $this->assertBadge('success', CrisisContinuityImpactStatus::Restored->label(), $html);
        foreach (['down', 'workaround', 'restored'] as $value) {
            $this->assertSame(1, substr_count($html, '<option value="' . $value . '" selected>'), $value);
        }
        $this->assertPageLacks('<option value="degraded" selected>', $html);
    }

    public function test_continuity_update_accepts_every_status_and_nothing_else(): void {
        $case = $this->crisis(CrisisCaseStatus::Recovery);
        $impact = $this->impact($case, CrisisContinuityImpactStatus::Down);

        $this->actingAs($this->admin)->put(route('crisis.bcm.update', [$case, $impact]), ['status' => 'kaputt'])->assertSessionHasErrors('status');
        foreach (CrisisContinuityImpactStatus::cases() as $status) {
            $this->actingAs($this->admin)->put(route('crisis.bcm.update', [$case, $impact]), ['status' => $status->value])->assertSessionHasNoErrors();
            $this->assertSame($status, $impact->fresh()->status);
        }
    }

    // ── Offline-Mappe und BCM-Bericht ────────────────────────────────────

    public function test_offline_bundle_carries_the_stored_status_values(): void {
        $case = $this->crisis(CrisisCaseStatus::Stabilized);
        $this->crisis(CrisisCaseStatus::AllClear, ['title' => 'Entwarnt']);
        $this->action($case, CrisisActionStatus::InProgress);
        $this->action($case, CrisisActionStatus::Done, ['title' => 'Erledigt']);

        $json = $this->actingAs($this->admin)->getJson(route('crisis.offline-bundle'))->assertOk()->json();

        $this->assertSame(['stabilized'], array_column($json['cases'], 'status'));
        $this->assertSame([['title' => 'Netz trennen', 'due_at' => null, 'assignee' => null, 'status' => 'in_progress']], $json['cases'][0]['actions']);
    }

    public function test_bcm_report_counts_pending_actions_and_ended_cases(): void {
        $active = $this->crisis(CrisisCaseStatus::Activated);
        $this->crisis(CrisisCaseStatus::AllClear);
        $this->crisis(CrisisCaseStatus::Closed);
        $this->crisis(CrisisCaseStatus::Discarded);
        $this->action($active, CrisisActionStatus::Open, ['due_at' => now()->subDay()]);
        $this->action($active, CrisisActionStatus::InProgress);
        $this->action($active, CrisisActionStatus::Done, ['due_at' => now()->subDay()]);
        $this->action($active, CrisisActionStatus::Cancelled);

        $report = app(CrisisBcmReportBuilder::class)->build();

        $this->assertSame(2, $report['actions_open']);
        $this->assertSame(1, $report['actions_overdue']);
        $this->assertSame(2, $report['cases_ended']);
        $this->assertSame(2, $report['cases_without_review']);
    }
}
