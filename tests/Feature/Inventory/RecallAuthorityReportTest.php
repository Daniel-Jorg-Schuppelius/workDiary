<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecallAuthorityReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{RecallKind, RecallMeasure, RecallRiskLevel, RecallStatus};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\Recall;
use App\Models\Platform\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-945: Angaben und Meldebogen für die Behördenmeldung eines Rückrufs. */
final class RecallAuthorityReportTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_authority_data_and_report(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Heizlüfter']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'HL-1']);
        $recall = Recall::query()->create([
            'organization_id' => $this->organization->id, 'number' => 'RUF-1', 'article_variant_id' => $variant->id,
            'kind' => RecallKind::Safety, 'status' => RecallStatus::Draft, 'title' => 'Überhitzung', 'reason' => 'Thermosicherung fehlerhaft.', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('recalls.authority.edit', $recall))->assertOk();
        $this->actingAs($admin)->put(route('recalls.authority.update', $recall), [
            'hazard_kind' => 'Brand', 'hazard_description' => 'Gehäuse kann sich entzünden.', 'risk_level' => 'serious', 'measure' => 'recall',
            'countries' => 'de, at', 'authority_name' => 'Marktüberwachung NRW', 'authority_reference' => 'AZ-17', 'authority_reported_on' => '2026-09-20',
            'contact_name' => 'Anna Qualität', 'contact_email' => 'qm@example.test',
        ])->assertSessionHas('success');

        $recall->refresh();
        $this->assertSame(RecallRiskLevel::Serious, $recall->risk_level);
        $this->assertSame(RecallMeasure::Recall, $recall->measure);
        $this->assertSame(['DE', 'AT'], $recall->countries);

        $response = $this->actingAs($admin)->get(route('recalls.authority.pdf', $recall))->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
