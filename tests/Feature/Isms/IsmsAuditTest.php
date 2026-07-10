<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsmsAuditTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Isms;

use App\Enums\Isms\{AuditStatus, CorrectiveActionStatus, FindingKind, FindingStatus};
use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Models\Isms\{IsmsAudit, IsmsAuditFinding, IsmsCorrectiveAction, IsmsScope};
use App\Models\Notification\NotificationRule;
use App\Models\{Organization, User};
use App\Services\Isms\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Audits inkl. Feststellungen und Korrekturmaßnahmen (Feature 046,
 * Inkrement C): Nummernvergabe (audit_no je Org, finding_no je Audit),
 * Statusketten (reportIssued NUR mit Durchführungszeitraum + Ergebnis),
 * Feststellungen nur bei laufendem Audit, Abschlussregeln (alle Maßnahmen
 * done/effective; Nichtkonformitäten brauchen eine wirksame Maßnahme),
 * Wirksamkeitsprüfung mit Pflicht-Notiz (ineffective ⇒ Feststellung
 * zurück auf inCorrection), Scanner-Event isms.correctiveActionOverdue
 * (Dedup), Permissions und Mandantengrenze.
 */
class IsmsAuditTest extends TestCase {
    use RefreshDatabase;

    public function test_store_creates_audit_with_sequential_numbers(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);
        $scope = IsmsScope::factory()->default()->create(['organization_id' => $admin->organization_id]);

        foreach (['Internes Audit 2026', 'Lieferantenaudit 2026'] as $title) {
            $this->actingAs($admin)
                ->post(route('isms.audits.store'), [
                    'scope' => $scope->sqid,
                    'title' => $title,
                    'kind' => 'internal',
                    'planned_on' => now()->addMonth()->toDateString(),
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $numbers = IsmsAudit::query()->orderBy('audit_no')->pluck('audit_no')->all();
        $this->assertSame([1, 2], $numbers, 'audit_no läuft je Organisation fortlaufend');

        /** @var IsmsAudit $audit */
        $audit = IsmsAudit::query()->firstOrFail();
        $this->assertSame(AuditStatus::Planned, $audit->status, 'Statuskette startet immer bei planned');
        $this->assertSame('A-1', $audit->displayNo());
    }

    public function test_status_chain_allows_forward_transitions_and_rollback(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin);

        foreach ([AuditStatus::InPreparation, AuditStatus::InProgress] as $target) {
            $this->actingAs($admin)
                ->post(route('isms.audits.transition', $audit), ['status' => $target->value])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
            $this->assertSame($target, $audit->refresh()->status);
        }

        // reportIssued erst mit Durchführungszeitraum + Zusammenfassung.
        $audit->update([
            'performed_from' => now()->subDays(2)->toDateString(),
            'performed_to' => now()->subDay()->toDateString(),
            'summary' => 'Keine wesentlichen Abweichungen.',
        ]);

        $this->actingAs($admin)
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::ReportIssued->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(AuditStatus::ReportIssued, $audit->refresh()->status);

        // Rücksprung reportIssued → inProgress ist erlaubt.
        $this->actingAs($admin)
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::InProgress->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(AuditStatus::InProgress, $audit->refresh()->status);
    }

    public function test_report_issued_without_result_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);

        // performed_from/to + summary fehlen ⇒ Serviceregel greift.
        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::ReportIssued->value])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(AuditStatus::InProgress, $audit->refresh()->status);

        // Service direkt: ValidationException mit klarer Meldung.
        try {
            app(AuditService::class)->transitionAudit($audit, AuditStatus::ReportIssued, $admin);
            $this->fail('Erwartete ValidationException blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_forbidden_transition_is_rejected(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin);

        // planned → inProgress überspringt die Kette.
        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::InProgress->value])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('status');

        $this->assertSame(AuditStatus::Planned, $audit->refresh()->status);
    }

    public function test_finding_requires_running_audit_and_numbers_per_audit(): void {
        $admin = User::factory()->admin()->create();
        $planned = $this->makeAudit($admin);

        // Bei geplantem Audit: Feststellung nicht erfassbar.
        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.findings.store', $planned), $this->findingPayload())
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('audit');
        $this->assertSame(0, IsmsAuditFinding::query()->count());

        // Bei laufendem Audit: erfassbar, finding_no läuft je Audit.
        $running = $this->makeAudit($admin, AuditStatus::InProgress);
        foreach (['Feststellung Eins', 'Feststellung Zwei'] as $title) {
            $this->actingAs($admin)
                ->post(route('isms.audits.findings.store', $running), $this->findingPayload(['title' => $title]))
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $numbers = $running->findings()->orderBy('finding_no')->pluck('finding_no')->all();
        $this->assertSame([1, 2], $numbers, 'finding_no läuft je Audit fortlaufend');

        // Zweites Audit beginnt wieder bei 1.
        $other = $this->makeAudit($admin, AuditStatus::InProgress);
        $this->actingAs($admin)
            ->post(route('isms.audits.findings.store', $other), $this->findingPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame([1], $other->findings()->pluck('finding_no')->all());
    }

    public function test_nonconformity_close_requires_effective_action(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);
        $finding = $this->makeFinding($admin, $audit, FindingKind::NonconformityMinor, FindingStatus::EffectivenessCheck);

        // Nur eine umgesetzte (done), aber keine wirksame Maßnahme ⇒ Abschluss verboten.
        IsmsCorrectiveAction::factory()->done()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
        ]);

        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.findings.transition', $finding), ['status' => FindingStatus::Closed->value])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('status');
        $this->assertSame(FindingStatus::EffectivenessCheck, $finding->refresh()->status);

        // Mit einer wirksamen Maßnahme ist der Abschluss zulässig.
        IsmsCorrectiveAction::factory()->effective()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
        ]);

        $this->actingAs($admin)
            ->post(route('isms.audits.findings.transition', $finding), ['status' => FindingStatus::Closed->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(FindingStatus::Closed, $finding->refresh()->status);
    }

    public function test_observation_close_rules(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);

        // Beobachtung ohne Maßnahmen: schließbar.
        $withoutActions = $this->makeFinding($admin, $audit, FindingKind::Observation, FindingStatus::EffectivenessCheck);
        $this->actingAs($admin)
            ->post(route('isms.audits.findings.transition', $withoutActions), ['status' => FindingStatus::Closed->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(FindingStatus::Closed, $withoutActions->refresh()->status);

        // Beobachtung mit offener Maßnahme: NICHT schließbar.
        $withOpenAction = $this->makeFinding($admin, $audit, FindingKind::Observation, FindingStatus::EffectivenessCheck);
        IsmsCorrectiveAction::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $withOpenAction->id,
        ]);

        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.findings.transition', $withOpenAction), ['status' => FindingStatus::Closed->value])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('status');
        $this->assertSame(FindingStatus::EffectivenessCheck, $withOpenAction->refresh()->status);
    }

    public function test_ineffective_requires_note_and_reverts_finding_to_correction(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);
        $finding = $this->makeFinding($admin, $audit, FindingKind::NonconformityMinor, FindingStatus::EffectivenessCheck);
        $action = IsmsCorrectiveAction::factory()->done()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
        ]);

        // Ohne Pflicht-Notiz: abgelehnt.
        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.actions.transition', $action), ['status' => CorrectiveActionStatus::Ineffective->value])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('effectiveness_note');
        $this->assertSame(CorrectiveActionStatus::Done, $action->refresh()->status);

        // Mit Notiz: Maßnahme ineffective + Feststellung zurück auf inCorrection.
        $this->actingAs($admin)
            ->post(route('isms.audits.actions.transition', $action), [
                'status' => CorrectiveActionStatus::Ineffective->value,
                'effectiveness_note' => 'Stichprobe zeigt erneute Abweichung.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(CorrectiveActionStatus::Ineffective, $action->refresh()->status);
        $this->assertSame('Stichprobe zeigt erneute Abweichung.', $action->effectiveness_note);
        $this->assertSame(FindingStatus::InCorrection, $finding->refresh()->status, 'ineffective setzt die Feststellung zurück');
    }

    public function test_effective_requires_note_and_done_sets_completed_on(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);
        $finding = $this->makeFinding($admin, $audit, FindingKind::NonconformityMinor, FindingStatus::InCorrection);
        $action = IsmsCorrectiveAction::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
            'completed_on' => null,
        ]);

        // open → done setzt completed_on automatisch.
        $this->actingAs($admin)
            ->post(route('isms.audits.actions.transition', $action), ['status' => CorrectiveActionStatus::Done->value])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(CorrectiveActionStatus::Done, $action->refresh()->status);
        $this->assertNotNull($action->completed_on);

        // done → effective NUR mit Notiz (Service direkt).
        try {
            app(AuditService::class)->transitionAction($action, CorrectiveActionStatus::Effective, $admin, null);
            $this->fail('Erwartete ValidationException blieb aus.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('effectiveness_note', $e->errors());
        }

        app(AuditService::class)->transitionAction($action, CorrectiveActionStatus::Effective, $admin, 'Wirksamkeit nachgewiesen.');
        $this->assertSame(CorrectiveActionStatus::Effective, $action->refresh()->status);
    }

    public function test_action_cannot_be_added_to_closed_finding(): void {
        $admin = User::factory()->admin()->create();
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);
        $finding = $this->makeFinding($admin, $audit, FindingKind::Observation, FindingStatus::Closed);

        $this->actingAs($admin)
            ->from(route('isms.audits.index'))
            ->post(route('isms.audits.actions.store', $finding), ['title' => 'Nachgelagerte Maßnahme'])
            ->assertRedirect(route('isms.audits.index'))
            ->assertSessionHasErrors('finding');

        $this->assertSame(0, $finding->correctiveActions()->count());
    }

    public function test_scanner_fires_corrective_action_overdue_exactly_once(): void {
        $admin = User::factory()->admin()->create();
        app()->instance('currentOrganization', $admin->organization);

        $owner = User::factory()->user()->create(['organization_id' => $admin->organization_id]);

        // Determinismus: nur In-App, Empfänger ausschließlich der
        // Maßnahmen-Verantwortliche (notify_affected).
        NotificationRule::factory()->forEvent(NotificationEvent::IsmsCorrectiveActionOverdue)->create([
            'organization_id' => $admin->organization_id,
            'channels' => [NotificationChannel::InApp->value],
            'notify_affected' => true,
            'recipient_roles' => [],
        ]);

        $audit = $this->makeAudit($admin, AuditStatus::InProgress);
        $finding = $this->makeFinding($admin, $audit, FindingKind::NonconformityMinor, FindingStatus::InCorrection);

        // Überfällig ⇒ Event; künftige Fälligkeit ⇒ kein Event.
        IsmsCorrectiveAction::factory()->overdue()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
            'owner_user_id' => $owner->id,
        ]);
        IsmsCorrectiveAction::factory()->create([
            'organization_id' => $admin->organization_id,
            'isms_audit_finding_id' => $finding->id,
            'owner_user_id' => $owner->id,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $owner->notifications()->count(), 'Dedup: genau eine Benachrichtigung');
        $data = (array) $owner->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::IsmsCorrectiveActionOverdue->value, $data['event'] ?? null);
    }

    public function test_regular_user_cannot_access_audits(): void {
        $user = User::factory()->user()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
        $audit = $this->makeAudit($admin);

        $this->actingAs($user)->get(route('isms.audits.index'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::InPreparation->value])
            ->assertForbidden();
    }

    public function test_geschaeftsfuehrung_can_view_but_not_manage(): void {
        $gf = User::factory()->geschaeftsfuehrung()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $gf->organization_id]);
        $audit = $this->makeAudit($admin, AuditStatus::InProgress);

        $this->actingAs($gf)->get(route('isms.audits.index'))->assertOk();

        $this->actingAs($gf)
            ->post(route('isms.audits.transition', $audit), ['status' => AuditStatus::ReportIssued->value])
            ->assertForbidden();
        $this->actingAs($gf)
            ->post(route('isms.audits.findings.store', $audit), $this->findingPayload())
            ->assertForbidden();

        $this->assertSame(AuditStatus::InProgress, $audit->refresh()->status);
        $this->assertSame(0, $audit->findings()->count());
    }

    public function test_cross_organization_audit_is_not_accessible(): void {
        $admin = User::factory()->admin()->create();
        $otherOrg = Organization::factory()->create(['slug' => 'isms-audit-cross']);
        $otherAdmin = User::factory()->admin()->create(['organization_id' => $otherOrg->id]);
        $foreignAudit = $this->makeAudit($otherAdmin, AuditStatus::InProgress);

        app()->instance('currentOrganization', $admin->organization);

        $this->actingAs($admin)
            ->post(route('isms.audits.transition', $foreignAudit), ['status' => AuditStatus::ReportIssued->value])
            ->assertNotFound();
        $this->actingAs($admin)
            ->post(route('isms.audits.findings.store', $foreignAudit), $this->findingPayload())
            ->assertNotFound();

        $this->assertSame(AuditStatus::InProgress, $foreignAudit->refresh()->status);
        $this->assertSame(0, $foreignAudit->findings()->count());
    }

    /** Audit im Default-Scope der Organisation des Users. */
    private function makeAudit(User $owner, AuditStatus $status = AuditStatus::Planned): IsmsAudit {
        app()->instance('currentOrganization', $owner->organization);

        $scope = IsmsScope::query()->firstOrCreate(
            ['organization_id' => $owner->organization_id, 'is_default' => true],
            ['name' => 'Gesamtorganisation'],
        );

        return IsmsAudit::factory()->status($status)->create([
            'organization_id' => $owner->organization_id,
            'isms_scope_id' => $scope->id,
        ]);
    }

    private function makeFinding(User $owner, IsmsAudit $audit, FindingKind $kind, FindingStatus $status): IsmsAuditFinding {
        app()->instance('currentOrganization', $owner->organization);

        return IsmsAuditFinding::factory()->kind($kind)->status($status)->create([
            'organization_id' => $owner->organization_id,
            'isms_audit_id' => $audit->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function findingPayload(array $overrides = []): array {
        return $overrides + [
            'kind' => FindingKind::Observation->value,
            'title' => 'Feststellung aus dem Audit',
            'description' => 'Beschreibung der Feststellung.',
        ];
    }
}
