<?php
/*
 * Created on   : Fri Jul 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ApplicationContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Applications;

use App\Models\Applications\ApplicationOpportunity;
use App\Models\User;
use App\Services\Applications\{ContractNegotiationService, RecruitingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 068, MVP-195–197: Vertragsverhandlung — Versionen append-only
 * mit Hash, Blocker-Punkte verhindern den Abschluss, zweistufige Freigabe
 * mit Selbstfreigabe-Sperre; Konditionen verschlüsselt.
 */
final class ApplicationContractTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
    }

    private function wonOpportunity(): ApplicationOpportunity {
        return ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Gewonnene Ausschreibung',
            'kind' => 'tender',
            'status' => 'won',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_negotiation_lifecycle_with_blockers_and_approvals(): void {
        $service = app(ContractNegotiationService::class);
        $opportunity = $this->wonOpportunity();
        $second = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $negotiation = $service->open($opportunity, 'Rahmenvertrag 2027', null, $this->admin);
        $this->assertSame(2, $negotiation->approvals()->count(), 'Zweistufige Freigabekette erwartet.');

        // Versionen: append-only, Hash über die Konditionen.
        $v1 = $service->addVersion($negotiation, 'draft', 'Erstentwurf', ['zahlungsziel' => '30 Tage'], $this->admin);
        $v2 = $service->addVersion($negotiation, 'counter', 'Gegenentwurf Kunde', ['zahlungsziel' => '60 Tage'], $this->admin);
        $this->assertSame([1, 2], [$v1->version, $v2->version]);
        $this->assertNotNull($v1->sha256);
        $this->assertSame(['zahlungsziel' => '60 Tage'], $v2->conditionsArray());

        // Blocker offen → Abschluss unmöglich, auch nach Freigaben.
        $service->addReviewItem($negotiation, 'Haftungsklausel weicht vom Angebot ab', 'blocker', null, $this->admin);

        // Selbstfreigabe-Sperre: Ersteller darf nicht freigeben.
        try {
            $service->approve($negotiation->refresh(), $this->admin);
            $this->fail('Selbstfreigabe wurde akzeptiert.');
        } catch (\RuntimeException) {
        }
        $service->approve($negotiation->refresh(), $second);
        $service->approve($negotiation->refresh(), $second);
        $this->assertSame('approved', $negotiation->fresh()->status);

        try {
            $service->conclude($negotiation->refresh(), 'concluded', null, $second);
            $this->fail('Abschluss trotz offenem Blocker.');
        } catch (\RuntimeException) {
        }

        // Blocker sichtbar entscheiden → Abschluss möglich.
        $item = $negotiation->reviewItems()->firstOrFail();
        $service->resolveReviewItem($negotiation, (int) $item->id, 'accepted', 'Risiko bewusst akzeptiert', $second);
        $service->conclude($negotiation->refresh(), 'concluded', null, $second);
        $this->assertSame('concluded', $negotiation->fresh()->decision);

        // Abgeschlossene Verhandlung ist eingefroren.
        try {
            $service->addVersion($negotiation->refresh(), 'final', null, [], $second);
            $this->fail('Version nach Abschluss akzeptiert.');
        } catch (\RuntimeException) {
        }
    }

    public function test_negotiation_requires_won_or_offer_context_and_encrypts_conditions(): void {
        $service = app(ContractNegotiationService::class);

        // Offene Ausschreibung: keine Verhandlung.
        $open = ApplicationOpportunity::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Noch offen',
            'kind' => 'tender',
            'status' => 'in_progress',
            'created_by' => $this->admin->id,
        ]);
        try {
            $service->open($open, 'Zu früh', null, $this->admin);
            $this->fail('Verhandlung vor Gewinnentscheidung akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Personal-Kontext: Konditionen liegen verschlüsselt in der DB.
        ['application' => $application] = app(RecruitingService::class)->intake(['candidate_name' => 'Kim', 'source' => 'website'], $this->admin);
        app(RecruitingService::class)->decide($application, 'offer', null, $this->admin);
        $negotiation = $service->open($application->refresh(), 'Arbeitsvertrag', null, $this->admin);
        $version = $service->addVersion($negotiation, 'draft', null, ['gehalt' => '52000 EUR', 'probezeit' => '6 Monate'], $this->admin);

        $raw = \Illuminate\Support\Facades\DB::table('application_contract_versions')->where('id', $version->id)->value('conditions');
        $this->assertStringNotContainsString('52000', (string) $raw);
        $this->assertSame('52000 EUR', $version->fresh()->conditionsArray()['gehalt']);
    }
}
