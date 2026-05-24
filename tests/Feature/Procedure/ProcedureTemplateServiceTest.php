<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procedure;

use App\Enums\Procedure\{ProcedureRiskLevel, ProcedureStepType};
use App\Exceptions\PublishedProcedureVersionLockedException;
use App\Models\{Organization, User};
use App\Services\Procedure\ProcedureTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProcedureTemplateServiceTest extends TestCase {
    use RefreshDatabase;

    private ProcedureTemplateService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = app(ProcedureTemplateService::class);
    }

    public function test_create_starts_with_initial_draft_version(): void {
        [$org, $user] = $this->makeOrgAndUser();

        $template = $this->service->create($org, $user, [
            'code' => 'UPDATE-LINUX',
            'name' => 'Linux-Update',
        ]);

        $this->assertSame($org->id, $template->organization_id);
        $this->assertCount(1, $template->versions);
        $version = $template->versions->first();
        $this->assertNotNull($version);
        $this->assertSame(1, $version->version);
        $this->assertNull($version->published_at);
        $this->assertSame(ProcedureRiskLevel::Normal, $version->risk_level);
    }

    public function test_add_step_def_on_published_version_throws(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->service->create($org, $user, ['code' => 'X1', 'name' => 'X1']);
        $version = $template->versions->first();
        $this->service->addStepDef($version, [
            'code' => 'first',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Bestätigen',
        ]);
        $this->service->publish($version, $user);

        $this->expectException(PublishedProcedureVersionLockedException::class);
        $this->service->addStepDef($version->fresh(), [
            'code' => 'second',
            'step_type' => ProcedureStepType::Confirm->value,
            'label' => 'Zweite',
        ]);
    }

    public function test_publish_closes_previous_version(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->service->create($org, $user, ['code' => 'X2', 'name' => 'X2']);
        $v1 = $template->versions->first();
        $this->service->publish($v1, $user, Carbon::parse('2026-01-01'));

        $v2 = $this->service->addVersion($template, $user);
        $this->service->publish($v2, $user, Carbon::parse('2026-02-15'));

        $v1Fresh = $v1->fresh();
        $v2Fresh = $v2->fresh();
        $this->assertSame('2026-02-14', $v1Fresh->valid_to->toDateString());
        $this->assertSame('2026-02-15', $v2Fresh->valid_from->toDateString());
        $this->assertNull($v2Fresh->valid_to);
    }

    public function test_current_version_for_returns_active_version_at_date(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->service->create($org, $user, ['code' => 'X3', 'name' => 'X3']);
        $v1 = $template->versions->first();
        $this->service->publish($v1, $user, Carbon::parse('2026-01-01'));
        $v2 = $this->service->addVersion($template, $user);
        $this->service->publish($v2, $user, Carbon::parse('2026-03-01'));

        $current = $this->service->currentVersionFor($template->fresh(), Carbon::parse('2026-02-10'));
        $this->assertNotNull($current);
        $this->assertSame($v1->id, $current->id);

        $later = $this->service->currentVersionFor($template->fresh(), Carbon::parse('2026-03-15'));
        $this->assertNotNull($later);
        $this->assertSame($v2->id, $later->id);
    }

    public function test_update_step_def_blocked_after_publish(): void {
        [$org, $user] = $this->makeOrgAndUser();
        $template = $this->service->create($org, $user, ['code' => 'X4', 'name' => 'X4']);
        $version = $template->versions->first();
        $step = $this->service->addStepDef($version, [
            'code' => 'a',
            'step_type' => ProcedureStepType::Text->value,
            'label' => 'A',
        ]);
        $this->service->publish($version, $user);

        $this->expectException(PublishedProcedureVersionLockedException::class);
        $this->service->updateStepDef($step->fresh(), ['label' => 'B']);
    }

    /** @return array{0: Organization, 1: User} */
    private function makeOrgAndUser(): array {
        $user = User::factory()->geschaeftsfuehrung()->create();
        return [$user->organization, $user];
    }
}
