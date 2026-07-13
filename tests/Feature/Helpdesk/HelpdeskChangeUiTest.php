<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskChangeUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{Approval, Asset, Change, ChangeTemplate, Organization, Problem, ServiceTicket, User};
use App\Services\ServiceTicket\ChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-157: Change-/CAB-UI — Standard-Change im UI nur aus
 * FREIGEGEBENER Vorlage (Fehlerpfad), Rollback-Pflicht bei normal,
 * Emergency-Abschluss ohne PIR scheitert, Asset-Verknüpfung mit harter
 * Tenant-Grenze, Change-Genehmigungsschritte in der GEMEINSAMEN Inbox
 * (inkl. Delegation), Org-Scope-404, Vorlagen-CRUD + Freigabe.
 */
final class HelpdeskChangeUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    private User $approverA;

    private User $approverB;

    private User $approverC;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->manager = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->approverA = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->approverB = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->approverC = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function template(array $overrides = []): ChangeTemplate {
        return ChangeTemplate::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Patchday',
            'rollback_plan' => 'Snapshot zurückspielen',
            ...$overrides,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function storePayload(array $overrides = []): array {
        return [
            'title' => 'Firewall-Firmware aktualisieren',
            'change_type' => 'normal',
            'rollback_plan' => 'Alte Firmware zurückflashen',
            ...$overrides,
        ];
    }

    public function test_index_and_show_are_org_scoped(): void {
        Change::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Eigener Change',
            'rollback_plan' => 'Rollback',
        ]);
        $foreign = Change::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'title' => 'Fremder Change',
            'rollback_plan' => 'Rollback',
        ]);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.changes.index'))
            ->assertOk()
            ->assertSee('Eigener Change')
            ->assertDontSee('Fremder Change');

        // Fremd-Org: Route-Binding läuft org-gescopt ins 404.
        $this->actingAs($this->manager)
            ->get(route('servicedesk.changes.show', $foreign->sqid))
            ->assertNotFound();

        // Ohne Recht: 403 auf der Anlage.
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)
            ->post(route('servicedesk.changes.store'), $this->storePayload())
            ->assertForbidden();
    }

    public function test_standard_change_requires_approved_template_via_ui(): void {
        // Ohne Vorlage: schon die Validierung lehnt ab (required_if).
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload([
                'change_type' => 'standard',
                'rollback_plan' => null,
            ]))
            ->assertSessionHasErrors('change_template_id');

        // Nicht freigegebene Vorlage: der Service lehnt ab (Fehlerpfad).
        $draft = $this->template(['name' => 'Entwurf', 'approved' => false]);
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload([
                'change_type' => 'standard',
                'rollback_plan' => null,
                'change_template_id' => $draft->sqid,
            ]))
            ->assertSessionHasErrors('change_type');
        $this->assertSame(0, Change::query()->count());

        // Freigegebene Vorlage: Change entsteht sofort genehmigt, Snapshot eingefroren.
        $approved = $this->template(['approved' => true, 'version' => 3]);
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload([
                'title' => 'Patchday Juli',
                'change_type' => 'standard',
                'rollback_plan' => null,
                'change_template_id' => $approved->sqid,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $change = Change::query()->where('title', 'Patchday Juli')->firstOrFail();
        $this->assertSame('approved', $change->status);
        $this->assertSame(3, $change->template_snapshot['version']);
        $this->assertSame('Snapshot zurückspielen', $change->rollback_plan);
    }

    public function test_normal_change_requires_rollback_plan_in_ui(): void {
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload(['rollback_plan' => null]))
            ->assertSessionHasErrors('rollback_plan');
        $this->assertSame(0, Change::query()->count());
    }

    public function test_change_approval_flows_through_shared_inbox_including_delegation(): void {
        $problem = Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Wiederkehrender Ausfall',
        ]);
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Firewall down',
        ]);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload([
                'title' => 'Genehmigungspflichtiger Change',
                'problem_id' => $problem->sqid,
                'ticket_ids' => [$ticket->sqid],
                'approval_steps' => [
                    ['type' => 'user', 'user' => $this->approverA->sqid],
                    ['type' => 'role', 'role' => 'teamleitung'],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $change = Change::query()->where('title', 'Genehmigungspflichtiger Change')->firstOrFail();
        $this->assertSame('pending_approval', $change->status);
        $this->assertSame((int) $problem->id, (int) $change->problem_id);
        $this->assertTrue($change->tickets()->whereKey($ticket->id)->exists());
        $this->assertSame(2, $change->approvals()->count());

        // Schritt 1 (persönlich A) erscheint in der GEMEINSAMEN Inbox …
        $this->actingAs($this->approverA)
            ->get(route('servicedesk.approvals.index'))
            ->assertOk()
            ->assertSee('Genehmigungspflichtiger Change');

        // … und ist entscheidbar.
        $step1 = $change->approvals()->where('step', 1)->firstOrFail();
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step1), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame('pending_approval', $change->fresh()->status);

        // Schritt 2 (Rolle) wird DELEGIERT: neuer offener Schritt GLEICHER Nummer.
        $step2 = $change->approvals()->where('step', 2)->firstOrFail();
        $this->actingAs($this->approverB)
            ->post(route('servicedesk.approvals.decide', $step2), [
                'decision' => 'delegated',
                'reason' => 'Urlaub — bitte übernehmen',
                'delegate' => $this->approverC->sqid,
            ])
            ->assertRedirect(route('servicedesk.approvals.index'));

        $delegated = Approval::query()
            ->where('approvable_type', Change::class)
            ->where('approvable_id', $change->id)
            ->where('step', 2)
            ->whereNull('decision')
            ->firstOrFail();
        $this->assertSame(['type' => 'user', 'value' => (int) $this->approverC->id], $delegated->approver_rule);

        // Der Delegat entscheidet — der Change ist damit genehmigt.
        $this->actingAs($this->approverC)
            ->post(route('servicedesk.approvals.decide', $delegated), ['decision' => 'approved'])
            ->assertRedirect(route('servicedesk.approvals.index'));
        $this->assertSame('approved', $change->fresh()->status);
    }

    public function test_emergency_complete_without_pir_fails_via_ui(): void {
        // Emergency mit ZWEI Schritten: der Service kürzt auf EINEN.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.store'), $this->storePayload([
                'title' => 'Notfall-Patch',
                'change_type' => 'emergency',
                'approval_steps' => [
                    ['type' => 'user', 'user' => $this->approverA->sqid],
                    ['type' => 'role', 'role' => 'teamleitung'],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $change = Change::query()->where('title', 'Notfall-Patch')->firstOrFail();
        $this->assertSame(1, $change->approvals()->count());

        $step = $change->approvals()->firstOrFail();
        $this->actingAs($this->approverA)
            ->post(route('servicedesk.approvals.decide', $step), ['decision' => 'approved']);
        $this->assertSame('approved', $change->fresh()->status);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.implement', $change))
            ->assertRedirect(route('servicedesk.changes.show', $change));
        $this->assertSame('implementing', $change->fresh()->status);

        // PIR-Zwang: Abschluss ohne Notizen scheitert (Service als zweite Linie).
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.complete', $change), ['outcome' => 'successful'])
            ->assertSessionHasErrors('pir_notes');
        $this->assertSame('implementing', $change->fresh()->status);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.complete', $change), [
                'outcome' => 'successful',
                'pir_notes' => 'PIR: Ursache dokumentiert, Monitoring ergänzt.',
            ])
            ->assertRedirect(route('servicedesk.changes.show', $change));

        $change = $change->fresh();
        $this->assertSame('done', $change->status);
        $this->assertSame('successful', $change->outcome);
        $this->assertNotNull($change->pir_done_at);
    }

    public function test_asset_attach_enforces_tenant_boundary(): void {
        $change = Change::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Asset-Change',
            'rollback_plan' => 'Rollback',
        ]);
        $own = Asset::factory()->create(['organization_id' => $this->organization->id]);
        $foreignOrg = Organization::factory()->create();
        $foreign = Asset::factory()->create(['organization_id' => $foreignOrg->id]);

        // Fremdes Asset: org-gescoptes Auflösen endet im Validierungsfehler.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.assets.store', $change), ['asset_id' => $foreign->sqid])
            ->assertSessionHasErrors('asset_id');
        $this->assertSame(0, $change->assets()->count());

        // Zweite Linie: der Service wirft bei Fremd-Org hart.
        try {
            app(ChangeService::class)->attachAsset($change, $foreign, $this->manager);
            $this->fail('Asset-Attach über Organisationsgrenzen wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Eigenes Asset: verknüpfen (idempotent) und wieder lösen.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.assets.store', $change), ['asset_id' => $own->sqid])
            ->assertRedirect(route('servicedesk.changes.show', $change));
        $this->actingAs($this->manager)
            ->post(route('servicedesk.changes.assets.store', $change), ['asset_id' => $own->sqid]);
        $this->assertSame(1, $change->assets()->count());

        $this->actingAs($this->manager)
            ->delete(route('servicedesk.changes.assets.destroy', [$change, $own]))
            ->assertRedirect(route('servicedesk.changes.show', $change));
        $this->assertSame(0, $change->assets()->count());
    }

    public function test_template_crud_with_versioning_and_approval(): void {
        // Anlage per Modal-POST — Vorlagen starten NICHT freigegeben.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.change-templates.store'), [
                'name' => 'Router-Patch',
                'rollback_plan' => 'Config-Backup einspielen',
            ])
            ->assertRedirect(route('servicedesk.change-templates.index'));

        $template = ChangeTemplate::query()->where('name', 'Router-Patch')->firstOrFail();
        $this->assertFalse($template->approved);
        $this->assertSame(1, $template->version);

        // Freigeben — erst jetzt taugt sie für Standard-Changes.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.change-templates.approve', $template))
            ->assertRedirect(route('servicedesk.change-templates.index'));
        $this->assertTrue($template->fresh()->approved);

        // Bearbeiten: Version-Bump + Freigabe-Rückzug.
        $this->actingAs($this->manager)
            ->patch(route('servicedesk.change-templates.update', $template), [
                'name' => 'Router-Patch v2',
                'rollback_plan' => 'Anders',
            ])
            ->assertRedirect(route('servicedesk.change-templates.index'));
        $template = $template->fresh();
        $this->assertSame(2, $template->version);
        $this->assertFalse($template->approved);

        // Löschen blockiert, sobald Changes auf der Vorlage stehen.
        $template->update(['approved' => true]);
        app(ChangeService::class)->submit(['title' => 'Standard aus Vorlage', 'change_type' => 'standard'], $this->manager, [], $template->fresh());
        $this->actingAs($this->manager)
            ->delete(route('servicedesk.change-templates.destroy', $template))
            ->assertSessionHas('error');
        $this->assertNotNull(ChangeTemplate::query()->find($template->id));

        // Ohne Recht: 403 auf der Vorlagen-Verwaltung; Fremd-Org: 404.
        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)
            ->get(route('servicedesk.change-templates.index'))
            ->assertForbidden();

        $foreignTemplate = ChangeTemplate::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'name' => 'Fremde Vorlage',
        ]);
        $this->actingAs($this->manager)
            ->post(route('servicedesk.change-templates.approve', $foreignTemplate->sqid))
            ->assertNotFound();
    }
}
