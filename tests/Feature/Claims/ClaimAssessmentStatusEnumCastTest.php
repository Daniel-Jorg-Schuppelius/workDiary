<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClaimAssessmentStatusEnumCastTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Enums\Claims\{ClaimAssessmentStatus, ClaimKind, ClaimVerdict};
use App\Models\Claims\ClaimCase;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Services\Claims\ClaimCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Konsolidierungs-Audit 2026-10, k3-10 (Welle 8): die Bewertung eines
 * Reklamationsfalls führt ihre Geltung als Enum. Gegen die frühere
 * Zeichenkette verglichen, erschiene jede Bewertung als abgelöst.
 */
final class ClaimAssessmentStatusEnumCastTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ClaimCase $case;

    protected function setUp(): void {
        parent::setUp();
        $this->travelTo('2026-10-05 12:00:00');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->case = app(ClaimCaseService::class)->open($this->organization, $this->admin, [
            'title' => 'Heizungsventil undicht',
            'source' => 'manual',
            'priority' => 'normal',
            'severity' => 'minor',
            'customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id])->id,
        ]);
    }

    private function supersededBadges(): int {
        $html = (string) $this->actingAs($this->admin)->get(route('claims.show', $this->case))->assertOk()->getContent();

        return (int) preg_match_all('/badge[^>]*>\s*' . preg_quote(e(__('abgelöst')), '/') . '\s*</u', $html);
    }

    public function test_only_earlier_assessments_are_marked_as_superseded(): void {
        $service = app(ClaimCaseService::class);

        $first = $service->assess($this->case, $this->admin, ClaimKind::WarrantyLegal, ClaimVerdict::Justified, 'Ventil innerhalb der Gewährleistung defekt.');
        $this->assertSame(ClaimAssessmentStatus::Active, $first->status);
        $this->assertSame(0, $this->supersededBadges());

        $this->travel(1)->hours();
        $second = $service->assess($this->case->fresh(), $this->admin, ClaimKind::Unfounded, ClaimVerdict::Rejected, 'Fehlbedienung laut Prüfbericht dokumentiert.');

        $this->assertSame(ClaimAssessmentStatus::Superseded, $first->fresh()->status);
        $this->assertTrue($this->case->fresh()->activeAssessment()?->is($second));
        $this->assertDatabaseHas('claim_assessments', ['id' => $first->id, 'status' => 'superseded']);
        $this->assertDatabaseHas('claim_assessments', ['id' => $second->id, 'status' => 'active']);
        $this->assertSame(1, $this->supersededBadges());
    }
}
