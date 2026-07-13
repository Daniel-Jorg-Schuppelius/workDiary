<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeExportPolicyTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\TimeExport\TimeExportStatus;
use App\Enums\User\Permission as P;
use App\Models\{Organization, TimeExport};
use App\Policies\TimeExportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\{BuildsPolicyActors, WithOrganization};
use Tests\TestCase;

/**
 * Lohn-/Zeitexporte (DATEV-Übergabe): export.time.create/deliver/delete als
 * getrennte Rechte, jede Objekt-Aktion hart organisationsgebunden; Statusfluss
 * Ready→Delivered (deliver), Ready/Delivered→Rejected (reject), Download nur
 * für downloadbare Stände, gelieferte Exporte sind unlöschbar (Nachweis).
 */
final class TimeExportPolicyTest extends TestCase {
    use BuildsPolicyActors;
    use RefreshDatabase;
    use WithOrganization;

    private TimeExportPolicy $policy;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsTeam($this->organization);
        $this->policy = new TimeExportPolicy;
    }

    private function export(TimeExportStatus $status, ?int $orgId = null): TimeExport {
        $export = new TimeExport;
        $export->organization_id = $orgId ?? $this->organization->id;
        $export->status = $status;

        return $export;
    }

    public function test_creator_creates_and_downloads_but_does_not_deliver(): void {
        $creator = $this->actorIn($this->organization, [P::ExportTimeCreate]);
        $ready = $this->export(TimeExportStatus::Ready);

        $this->assertTrue($this->policy->viewAny($creator));
        $this->assertTrue($this->policy->view($creator, $ready));
        $this->assertTrue($this->policy->create($creator));
        $this->assertTrue($this->policy->download($creator, $ready));
        $this->assertFalse($this->policy->deliver($creator, $ready), 'Liefern verlangt export.time.deliver.');
        $this->assertFalse($this->policy->reject($creator, $ready));
        $this->assertFalse($this->policy->delete($creator, $ready), 'Löschen verlangt export.time.delete.');
    }

    public function test_deliverer_follows_status_machine(): void {
        $deliverer = $this->actorIn($this->organization, [P::ExportTimeDeliver]);
        $preparing = $this->export(TimeExportStatus::Preparing);
        $ready = $this->export(TimeExportStatus::Ready);
        $delivered = $this->export(TimeExportStatus::Delivered);

        $this->assertTrue($this->policy->deliver($deliverer, $ready));
        $this->assertFalse($this->policy->deliver($deliverer, $preparing));
        $this->assertFalse($this->policy->deliver($deliverer, $delivered));
        $this->assertTrue($this->policy->reject($deliverer, $ready));
        $this->assertTrue($this->policy->reject($deliverer, $delivered));
        $this->assertFalse($this->policy->download($deliverer, $preparing), 'Preparing ist nicht downloadbar.');
        $this->assertTrue($this->policy->download($deliverer, $delivered));
    }

    public function test_delivered_exports_are_undeletable_evidence(): void {
        $deleter = $this->actorIn($this->organization, [P::ExportTimeDelete]);

        $this->assertTrue($this->policy->delete($deleter, $this->export(TimeExportStatus::Ready)));
        $this->assertFalse($this->policy->delete($deleter, $this->export(TimeExportStatus::Delivered)), 'Gelieferter Export ist Nachweis.');
    }

    public function test_foreign_org_is_denied_even_with_permissions(): void {
        $foreignOrg = Organization::factory()->create();
        $attacker = $this->actorIn($foreignOrg, [P::ExportTimeCreate, P::ExportTimeDeliver, P::ExportTimeDelete]);
        $ready = $this->export(TimeExportStatus::Ready); // Primär-Org

        $this->actAsTeam($foreignOrg);
        $this->assertFalse($this->policy->view($attacker, $ready));
        $this->assertFalse($this->policy->download($attacker, $ready));
        $this->assertFalse($this->policy->deliver($attacker, $ready));
        $this->assertFalse($this->policy->reject($attacker, $ready));
        $this->assertFalse($this->policy->delete($attacker, $ready));
    }

    public function test_orgless_or_permissionless_user_is_denied(): void {
        $this->assertFalse($this->policy->viewAny($this->actorIn($this->organization)));
        $this->assertFalse($this->policy->viewAny($this->orglessActor()));
    }
}
