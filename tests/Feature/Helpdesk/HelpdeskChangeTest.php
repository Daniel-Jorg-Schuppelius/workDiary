<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskChangeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Models\{Change, ChangeTemplate, Organization, User};
use App\Services\ServiceTicket\ChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P7 (MVP-157): Typ-Regeln (standard nur aus freigegebener
 * Vorlage, Rollback-Pflicht bei normal/emergency), verkürzte
 * Emergency-Kette + PIR-Zwang, Template-Snapshot-Versionierung,
 * generische Genehmigungen (eine Mechanik, Selbstfreigabe-Sperre).
 */
final class HelpdeskChangeTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $actor;

    private User $approver;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->actor = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
        $this->approver = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    public function test_type_rules_are_enforced(): void {
        $service = app(ChangeService::class);

        // Normal ohne Rollback-Plan → abgelehnt.
        try {
            $service->submit(['title' => 'Ohne Rollback', 'change_type' => 'normal'], $this->actor);
            $this->fail('Normal-Change ohne Rollback-Plan wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        // Standard ohne freigegebene Vorlage → abgelehnt.
        $draftTemplate = ChangeTemplate::query()->create(['organization_id' => $this->org->id, 'name' => 'Entwurf']);
        try {
            $service->submit(['title' => 'Standard', 'change_type' => 'standard'], $this->actor, [], $draftTemplate);
            $this->fail('Standard-Change aus nicht freigegebener Vorlage wurde akzeptiert.');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, Change::query()->count());
    }

    public function test_standard_change_freezes_template_snapshot(): void {
        $template = ChangeTemplate::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Patchday',
            'rollback_plan' => 'Snapshot zurückspielen',
            'approved' => true,
            'version' => 3,
        ]);

        $change = app(ChangeService::class)->submit(['title' => 'Patchday Juli', 'change_type' => 'standard'], $this->actor, [], $template);

        // Standard: sofort approved, Snapshot eingefroren.
        $this->assertSame('approved', $change->status);
        $this->assertSame(3, $change->template_snapshot['version']);

        // Vorlagenänderung (neue Version) deutet den Change nicht um.
        $template->update(['rollback_plan' => 'Anders', 'version' => 4]);
        $this->assertSame(3, $change->fresh()->template_snapshot['version']);
        $this->assertSame('Snapshot zurückspielen', $change->fresh()->rollback_plan);
    }

    public function test_emergency_change_shortens_chain_and_forces_pir(): void {
        $service = app(ChangeService::class);

        $change = $service->submit([
            'title' => 'Notfall-Patch',
            'change_type' => 'emergency',
            'rollback_plan' => 'Backup einspielen',
        ], $this->actor, [
            ['approver' => ['type' => 'role', 'value' => 'teamleitung']],
            ['approver' => ['type' => 'role', 'value' => 'admin']],
        ]);

        // Verkürzte Kette: genau EIN Schritt.
        $this->assertSame(1, $change->approvals()->count());

        // Selbstfreigabe gesperrt (eine Mechanik).
        $step = $change->approvals()->firstOrFail();
        try {
            $service->decide($step, $this->actor, 'approved');
            $this->fail('Selbstfreigabe wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        $change = $service->decide($step, $this->approver, 'approved');
        $this->assertSame('approved', $change->status);

        $change = $service->implement($change, $this->actor);

        // PIR-Zwang: Abschluss ohne PIR-Notizen scheitert.
        try {
            $service->complete($change, $this->actor, 'successful');
            $this->fail('Emergency-Abschluss ohne PIR wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        $change = $service->complete($change, $this->actor, 'successful', 'PIR: Ursache dokumentiert, Monitoring ergänzt.');
        $this->assertSame('done', $change->status);
        $this->assertSame('successful', $change->outcome);
        $this->assertNotNull($change->pir_done_at);
    }

    public function test_rejection_cancels_change(): void {
        $service = app(ChangeService::class);
        $change = $service->submit([
            'title' => 'Riskant',
            'change_type' => 'normal',
            'rollback_plan' => 'Rollback dokumentiert',
        ], $this->actor, [['approver' => ['type' => 'role', 'value' => 'teamleitung']]]);

        $change = $service->decide($change->approvals()->firstOrFail(), $this->approver, 'rejected', 'Zu riskant im Quartal');

        $this->assertSame('cancelled', $change->status);
        $this->assertSame('cancelled', $change->outcome);
    }
}
